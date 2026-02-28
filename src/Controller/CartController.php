<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Service\CouponService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/cart')]
final class CartController extends AbstractController
{
    #[Route('/', name: 'app_cart_index')]
    public function index(SessionInterface $session, ProductRepository $productRepository, CouponService $couponService): Response
    {
        $cart = $session->get('cart', []);
        $cartItems = [];
        $total = 0;

        foreach ($cart as $productId => $quantity) {
            $product = $productRepository->find($productId);
            if ($product) {
                $subtotal = $product->getPrice() * $quantity;
                $cartItems[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'subtotal' => $subtotal,
                ];
                $total += $subtotal;
            }
        }

        $user = $this->getUser();

        // Best available coupon to display
        $userCoupon = $user ? $couponService->getBestCoupon($user) : null;
        $appliedCouponCode = $session->get('applied_coupon_code');
        $appliedCoupon = null;
        if ($appliedCouponCode && $user) {
            $appliedCoupon = $couponService->validateCode($appliedCouponCode, $user);
            if (!$appliedCoupon) {
                $session->remove('applied_coupon_code');
                $appliedCouponCode = null;
            }
        }

        // --- Compute discount (only if a coupon is applied) ---
        $discountRate   = 0;
        $discountAmount = 0;
        $discountBase   = 0;   // portion of cart the coupon applies to
        $discountType   = null;

        if ($appliedCoupon) {
            $discountRate = $appliedCoupon->getDiscountRate();
            $discountType = 'coupon';

            if ($appliedCoupon->getCategoryName()) {
                // Category-restricted: compute base only from matching items
                foreach ($cartItems as $item) {
                    if ($item['product']->getCategory()?->getName() === $appliedCoupon->getCategoryName()) {
                        $discountBase += $item['subtotal'];
                    }
                }
            } else {
                $discountBase = $total;
            }

            $discountAmount = round($discountBase * $discountRate, 2);
        }

        $totalAfterDiscount = round($total - $discountAmount, 2);

        // --- Wallet ---
        $walletBalance  = $user ? $user->getWalletBalance() : 0.0;
        $walletApplied  = (bool) $session->get('apply_wallet', false);
        $walletDiscount = 0.0;
        if ($walletApplied && $walletBalance > 0 && $totalAfterDiscount > 0) {
            $walletDiscount = round(min($walletBalance, $totalAfterDiscount), 2);
        }
        $finalTotal = round($totalAfterDiscount - $walletDiscount, 2);

        return $this->render('cart/index.html.twig', [
            'cartItems'          => $cartItems,
            'total'              => $total,
            'discountRate'       => $discountRate,
            'discountAmount'     => $discountAmount,
            'discountBase'       => $discountBase,
            'discountType'       => $discountType,
            'totalAfterDiscount' => $totalAfterDiscount,
            'userCoupon'         => $userCoupon,
            'appliedCoupon'      => $appliedCoupon,
            'walletBalance'      => $walletBalance,
            'walletApplied'      => $walletApplied,
            'walletDiscount'     => $walletDiscount,
            'finalTotal'         => $finalTotal,
            'userReputation'     => $user ? $user->getReputation() : 0,
        ]);
    }

    #[Route('/coupon/apply', name: 'app_cart_apply_coupon', methods: ['POST'])]
    public function applyCoupon(Request $request, SessionInterface $session, CouponService $couponService): Response
    {
        $code = strtoupper(trim($request->request->get('coupon_code', '')));
        $user = $this->getUser();

        if (!$user || !$code) {
            $this->addFlash('warning', 'Code coupon invalide.');
            return $this->redirectToRoute('app_cart_index');
        }

        $coupon = $couponService->validateCode($code, $user);

        if (!$coupon) {
            $this->addFlash('danger', 'Ce code coupon est invalide ou a déjà été utilisé.');
        } else {
            $session->set('applied_coupon_code', $coupon->getCode());
            $this->addFlash('success', sprintf('Coupon %s appliqué — %.0f%% de réduction !', $coupon->getCode(), $coupon->getDiscountRate() * 100));
        }

        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/coupon/remove', name: 'app_cart_remove_coupon')]
    public function removeCoupon(SessionInterface $session): Response
    {
        $session->remove('applied_coupon_code');
        $this->addFlash('info', 'Coupon retiré.');
        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/wallet/convert', name: 'app_cart_wallet_convert', methods: ['POST'])]
    public function walletConvert(Request $request, SessionInterface $session, CouponService $couponService): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_cart_index');
        }

        $points = (int) $request->request->get('points_to_convert', 0);
        $credit = $couponService->convertPointsToWallet($user, $points);

        if ($credit > 0) {
            $this->addFlash('success', sprintf('%.0f pts convertis en %.2f DT de crédit portefeuille !', $points, $credit));
        } else {
            $this->addFlash('warning', 'Conversion impossible. Vérifiez que vous avez au moins 100 pts et saisissez un multiple de 100.');
        }

        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/wallet/apply', name: 'app_cart_wallet_apply')]
    public function walletApply(SessionInterface $session): Response
    {
        $session->set('apply_wallet', true);
        $this->addFlash('success', 'Crédit portefeuille appliqué à votre commande.');
        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/wallet/remove', name: 'app_cart_wallet_remove')]
    public function walletRemove(SessionInterface $session): Response
    {
        $session->remove('apply_wallet');
        $this->addFlash('info', 'Crédit portefeuille retiré.');
        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/add/{id}', name: 'app_cart_add')]
    public function add(int $id, SessionInterface $session, ProductRepository $productRepository, Request $request): Response
    {
        $product = $productRepository->find($id);
        if (!$product) {
            throw $this->createNotFoundException('Product not found');
        }

        $cart = $session->get('cart', []);

        $currentQty = $cart[$id] ?? 0;

        if ($currentQty >= $product->getQuantity()) {
            $this->addFlash('warning', sprintf('Stock insuffisant pour "%s" (max: %d)', $product->getLabel(), $product->getQuantity()));
            $referer = $request->headers->get('referer');
            if ($referer && str_contains($referer, '/dashboard')) {
                return $this->redirectToRoute('app_dashboard');
            }
            return $this->redirectToRoute('app_cart_index');
        }

        $cart[$id] = $currentQty + 1;

        $session->set('cart', $cart);

        $this->addFlash('success', sprintf('"%s" a été ajouté au panier !', $product->getLabel()));

        // If request comes from dashboard, redirect back there
        $referer = $request->headers->get('referer');
        if ($referer && str_contains($referer, '/dashboard')) {
            return $this->redirectToRoute('app_dashboard');
        }

        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/remove/{id}', name: 'app_cart_remove')]
    public function remove(int $id, SessionInterface $session): Response
    {
        $cart = $session->get('cart', []);

        if (isset($cart[$id])) {
            unset($cart[$id]);
        }

        $session->set('cart', $cart);

        $this->addFlash('info', 'Produit retiré du panier.');

        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/decrease/{id}', name: 'app_cart_decrease')]
    public function decrease(int $id, SessionInterface $session): Response
    {
        $cart = $session->get('cart', []);

        if (isset($cart[$id])) {
            $cart[$id]--;
            if ($cart[$id] <= 0) {
                unset($cart[$id]);
            }
        }

        $session->set('cart', $cart);

        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/increase/{id}', name: 'app_cart_increase')]
    public function increase(int $id, SessionInterface $session, ProductRepository $productRepository): Response
    {
        $product = $productRepository->find($id);
        $cart = $session->get('cart', []);

        if (isset($cart[$id])) {
            if ($cart[$id] >= $product->getQuantity()) {
                $this->addFlash('warning', sprintf('Stock maximum atteint pour "%s" (%d)', $product->getLabel(), $product->getQuantity()));
            } else {
                $cart[$id]++;
            }
        }

        $session->set('cart', $cart);

        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/clear', name: 'app_cart_clear')]
    public function clear(SessionInterface $session): Response
    {
        $session->remove('cart');
        $session->remove('applied_coupon_code');

        $this->addFlash('info', 'Le panier a été vidé.');

        return $this->redirectToRoute('app_cart_index');
    }
}
