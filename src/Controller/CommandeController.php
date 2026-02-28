<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\LigneCommande;
use App\Repository\CommandeRepository;
use App\Repository\ProductRepository;
use App\Service\CouponService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/commande')]
final class CommandeController extends AbstractController
{
    #[Route('/', name: 'app_commande_index')]
    public function index(CommandeRepository $commandeRepository): Response
    {
        $commandes = $commandeRepository->findBy(
            ['user' => $this->getUser()],
            ['createdAt' => 'DESC']
        );

        return $this->render('commande/index.html.twig', [
            'commandes' => $commandes,
        ]);
    }

    #[Route('/checkout', name: 'app_commande_checkout')]
    public function checkout(
        SessionInterface $session,
        ProductRepository $productRepository,
        EntityManagerInterface $entityManager,
        NotificationService $notificationService,
        CouponService $couponService
    ): Response {
        $cart = $session->get('cart', []);

        if (empty($cart)) {
            $this->addFlash('warning', 'Votre panier est vide !');
            return $this->redirectToRoute('app_cart_index');
        }

        // Validate stock availability before creating order
        foreach ($cart as $productId => $quantity) {
            $product = $productRepository->find($productId);
            if ($product && $quantity > $product->getQuantity()) {
                $this->addFlash('danger', sprintf(
                    'Stock insuffisant pour "%s" : %d demandé(s), %d disponible(s)',
                    $product->getLabel(), $quantity, $product->getQuantity()
                ));
                return $this->redirectToRoute('app_cart_index');
            }
        }

        $commande = new Commande();
        $commande->setUser($this->getUser());

        $total = 0;
        $pointsEarned = 0;

        foreach ($cart as $productId => $quantity) {
            $product = $productRepository->find($productId);
            if ($product) {
                $ligneCommande = new LigneCommande();
                $ligneCommande->setProduct($product);
                $ligneCommande->setQuantity($quantity);
                $ligneCommande->setPrix($product->getPrice());

                $commande->addLigneCommande($ligneCommande);
                $total += $product->getPrice() * $quantity;

                // Deduct stock from product
                $product->setQuantity($product->getQuantity() - $quantity);

                // 1 reputation point per DT spent on any non-boycotted (shop) product
                $pointsEarned += intval(floor($product->getPrice() * $quantity));
            }
        }

        $commande->setTotal($total);

        // --- Apply discount: applied coupon takes priority ---
        $user = $this->getUser();
        $appliedCouponCode = $session->get('applied_coupon_code');
        $coupon = null;

        if ($appliedCouponCode && $user) {
            $coupon = $couponService->validateCode($appliedCouponCode, $user);
        }

        if ($coupon) {
            // Compute discount (may be category-restricted)
            $discountBase = $total;
            if ($coupon->getCategoryName()) {
                $discountBase = 0;
                foreach ($commande->getLigneCommandes() as $ligne) {
                    if ($ligne->getProduct()->getCategory()?->getName() === $coupon->getCategoryName()) {
                        $discountBase += $ligne->getSubtotal();
                    }
                }
            }
            $discountAmount = round($discountBase * $coupon->getDiscountRate(), 2);
            $commande->setDiscountRate($coupon->getDiscountRate());
            $commande->setDiscountAmount($discountAmount);
            $commande->setCouponCode($coupon->getCode());
            $commande->setTotal(round($total - $discountAmount, 2));
        }

        // --- Apply wallet credit (100 pts = 10 DT) ---
        if ($user && $session->get('apply_wallet', false)) {
            $walletBalance = $user->getWalletBalance();
            $currentTotal  = $commande->getTotal();
            if ($walletBalance > 0 && $currentTotal > 0) {
                $walletUsed = round(min($walletBalance, $currentTotal), 2);
                $commande->setWalletDiscount($walletUsed);
                $commande->setTotal(round($currentTotal - $walletUsed, 2));
                $user->setWalletBalance($walletBalance - $walletUsed);
            }
        }

        $entityManager->persist($commande);
        $entityManager->flush();

        // Mark coupon as used and clear from session
        if ($coupon) {
            $couponService->markAsUsed($coupon);
            $session->remove('applied_coupon_code');
        }
        $session->remove('apply_wallet');

        // Award reputation points and check 200-pt milestones
        if ($pointsEarned > 0 && $user) {
            $newCoupons = $couponService->awardPointsAndCheckMilestone($user, $pointsEarned);
            $entityManager->flush();

            foreach ($newCoupons as $newCoupon) {
                $notificationService->create(
                    $user,
                    sprintf('🎉 +200 pts atteints ! Coupon de 50%% débloqué : %s — valable sur votre prochaine commande.', $newCoupon->getCode()),
                    'coupon',
                    $this->generateUrl('app_cart_index'),
                    'bi-ticket-perforated-fill'
                );
            }
        }

        // Notify the user about their order
        $notificationService->create(
            $this->getUser(),
            sprintf('Votre commande #%d a été passée avec succès ! Total : %.2f €', $commande->getId(), $commande->getTotal()),
            'order',
            $this->generateUrl('app_commande_show', ['id' => $commande->getId()]),
            'bi-bag-check'
        );

        // Notify admins about the new order
        $notificationService->notifyUsersWithRole(
            sprintf('Nouvelle commande #%d de %s — %.2f €', $commande->getId(), $this->getUser()->getEmail(), $commande->getTotal()),
            'order',
            null,
            'bi-cart-check',
            ['ROLE_ADMIN']
        );

        // Check for low stock and notify admins
        foreach ($cart as $productId => $quantity) {
            $product = $productRepository->find($productId);
            if ($product && $product->getQuantity() <= 5 && $product->getQuantity() > 0) {
                $notificationService->notifyUsersWithRole(
                    sprintf('⚠️ Stock faible : "%s" — %d restant(s)', $product->getLabel(), $product->getQuantity()),
                    'stock',
                    null,
                    'bi-exclamation-triangle',
                    ['ROLE_ADMIN']
                );
            } elseif ($product && $product->getQuantity() <= 0) {
                $notificationService->notifyUsersWithRole(
                    sprintf('🚫 Rupture de stock : "%s"', $product->getLabel()),
                    'warning',
                    null,
                    'bi-x-octagon',
                    ['ROLE_ADMIN']
                );
            }
        }

        // Clear the cart
        $session->remove('cart');

        $this->addFlash('success', sprintf('Commande #%d passée avec succès !', $commande->getId()));

        return $this->redirectToRoute('app_commande_show', ['id' => $commande->getId()]);
    }

    #[Route('/{id}', name: 'app_commande_show')]
    public function show(Commande $commande): Response
    {
        // Security: only allow user to see their own orders
        if ($commande->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas voir cette commande.');
        }

        return $this->render('commande/show.html.twig', [
            'commande' => $commande,
        ]);
    }
}
