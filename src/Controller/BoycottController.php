<?php

namespace App\Controller;

use App\Entity\Alternative;
use App\Entity\BoycottProduct;
use App\Entity\Product;
use App\Entity\Vote;
use App\Form\AlternativeType;
use App\Form\BoycottProductType;
use App\Repository\AlternativeRepository;
use App\Repository\BoycottProductRepository;
use App\Repository\VoteRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/boycott')]
class BoycottController extends AbstractController
{
    // ═══════════════════════════════════════════
    //  PUBLIC LISTING (approved boycotts only)
    // ═══════════════════════════════════════════

    #[Route('', name: 'app_boycott_index', methods: ['GET'])]
    public function index(Request $request, BoycottProductRepository $repo, VoteRepository $voteRepo): Response
    {
        $query  = $request->query->get('q');
        $reason = $request->query->get('reason');
        $level  = $request->query->get('level');

        $boycotts = $repo->findApproved($query, $reason, $level);

        // Pre-load user votes for display
        $userVotes = [];
        if ($this->getUser()) {
            $userVotes = $voteRepo->findUserBoycottVotes($this->getUser());
        }

        return $this->render('boycott/index.html.twig', [
            'boycotts'       => $boycotts,
            'userVotes'      => $userVotes,
            'currentSearch'  => $query,
            'currentReason'  => $reason,
            'currentLevel'   => $level,
            'pendingCount'   => $this->isGranted('ROLE_ADMIN') ? $repo->countByStatus(BoycottProduct::STATUS_PENDING) : 0,
        ]);
    }

    // ═══════════════════════════════════════════
    //  SHOW SINGLE BOYCOTT + ALTERNATIVES TABLE
    // ═══════════════════════════════════════════

    #[Route('/{id}', name: 'app_boycott_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(BoycottProduct $boycott, AlternativeRepository $altRepo, VoteRepository $voteRepo): Response
    {
        // Show all to admin, only approved to regular users
        $alternatives = $this->isGranted('ROLE_ADMIN')
            ? $altRepo->findForBoycott($boycott)
            : array_filter($altRepo->findForBoycott($boycott), fn($a) => $a->isApproved());

        $userAltVotes = [];
        $userBoycottVote = null;
        if ($this->getUser()) {
            $userAltVotes = $voteRepo->findUserAlternativeVotesForBoycott($this->getUser(), $boycott);
            $vote = $voteRepo->findUserBoycottVote($this->getUser(), $boycott);
            $userBoycottVote = $vote?->getValue();
        }

        return $this->render('boycott/show.html.twig', [
            'boycott'          => $boycott,
            'alternatives'     => $alternatives,
            'userAltVotes'     => $userAltVotes,
            'userBoycottVote'  => $userBoycottVote,
        ]);
    }

    // ═══════════════════════════════════════════
    //  SUGGEST A BOYCOTT (any logged-in user)
    // ═══════════════════════════════════════════

    #[IsGranted('ROLE_USER')]
    #[Route('/suggest', name: 'app_boycott_suggest', methods: ['GET', 'POST'])]
    public function suggest(Request $request, EntityManagerInterface $em, SluggerInterface $slugger, NotificationService $notifService): Response
    {
        $boycott = new BoycottProduct();
        $form = $this->createForm(BoycottProductType::class, $boycott);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle image upload
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('boycott_directory'),
                        $newFilename
                    );
                    $boycott->setImage($newFilename);
                } catch (FileException $e) {
                    // silently continue without image
                }
            }

            $boycott->setSubmittedBy($this->getUser());
            $boycott->setStatus(BoycottProduct::STATUS_PENDING);

            $em->persist($boycott);

            // Award reputation points for submitting
            $this->getUser()->addReputation(2);

            $em->flush();

            // Notify admins
            $notifService->notifyUsersWithRole(
                'Nouvelle suggestion de boycott : ' . $boycott->getName(),
                'boycott_suggestion',
                null,
                'bi-flag',
                ['ROLE_ADMIN']
            );

            $this->addFlash('success', 'Votre suggestion a été soumise et sera examinée par un administrateur.');
            return $this->redirectToRoute('app_boycott_index');
        }

        return $this->render('boycott/suggest.html.twig', [
            'form' => $form,
        ]);
    }

    // ═══════════════════════════════════════════
    //  SUGGEST AN ALTERNATIVE (any logged-in user)
    // ═══════════════════════════════════════════

    #[IsGranted('ROLE_USER')]
    #[Route('/{id}/alternative', name: 'app_boycott_add_alternative', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function addAlternative(BoycottProduct $boycott, Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $alternative = new Alternative();
        $alternative->setBoycottProduct($boycott);

        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $form = $this->createForm(AlternativeType::class, $alternative, [
            'show_for_sale'    => $isAdmin,
            'for_sale_default' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle image upload
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('boycott_directory'),
                        $newFilename
                    );
                    $alternative->setImage($newFilename);
                } catch (FileException $e) {
                    // silently continue
                }
            }

            $alternative->setSuggestedBy($this->getUser());

            if ($isAdmin) {
                // Admin submissions are approved immediately
                $alternative->setStatus(\App\Entity\Alternative::STATUS_APPROVED);
                $em->persist($alternative);
                // Only create a store Product if the admin chose "forSale"
                $forSale = (bool) $form->get('forSale')->getData();
                if ($forSale) {
                    $this->createProductFromAlternative($alternative, $em);
                    $flashMsg = 'Alternative "' . $alternative->getName() . '" ajoutée et disponible à la vente dans la boutique.';
                } else {
                    $alternative->setPrice(null);
                    $flashMsg = 'Alternative "' . $alternative->getName() . '" ajoutée comme information uniquement (non vendue).';
                }
                $this->getUser()->addReputation(3);
                $em->flush();
                $this->addFlash('success', $flashMsg);
            } else {
                $alternative->setStatus(\App\Entity\Alternative::STATUS_PENDING);
                $em->persist($alternative);
                $this->getUser()->addReputation(3);
                $em->flush();
                $this->addFlash('success', 'Votre alternative a été soumise et sera examinée par un administrateur avant d\'apparaître dans la boutique !');
            }

            return $this->redirectToRoute('app_boycott_show', ['id' => $boycott->getId()]);
        }

        return $this->render('boycott/add_alternative.html.twig', [
            'boycott' => $boycott,
            'form'    => $form,
        ]);
    }

    // ═══════════════════════════════════════════
    //  VOTE (AJAX endpoint)
    // ═══════════════════════════════════════════

    #[IsGranted('ROLE_USER')]
    #[Route('/vote', name: 'app_boycott_vote', methods: ['POST'])]
    public function vote(Request $request, EntityManagerInterface $em, VoteRepository $voteRepo, BoycottProductRepository $boycottRepo, AlternativeRepository $altRepo): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $type     = $data['type'] ?? null;       // 'boycott' or 'alternative'
        $targetId = $data['targetId'] ?? null;
        $value    = ($data['value'] ?? 1) >= 0 ? 1 : -1;

        if (!$type || !$targetId) {
            return $this->json(['error' => 'Invalid request'], 400);
        }

        $user = $this->getUser();

        if ($type === 'boycott') {
            $boycott = $boycottRepo->find($targetId);
            if (!$boycott) {
                return $this->json(['error' => 'Not found'], 404);
            }

            $existingVote = $voteRepo->findUserBoycottVote($user, $boycott);

            if ($existingVote) {
                if ($existingVote->getValue() === $value) {
                    // Same vote → remove it (toggle off)
                    $em->remove($existingVote);
                    $user->addReputation(-1);
                    $em->flush();
                    $newScore = $voteRepo->calculateBoycottScore($boycott);
                    $boycott->setVoteScore($newScore);
                    $em->flush();
                    return $this->json(['score' => $newScore, 'userVote' => 0]);
                } else {
                    // Different vote → change it
                    $existingVote->setValue($value);
                    $em->flush();
                    $newScore = $voteRepo->calculateBoycottScore($boycott);
                    $boycott->setVoteScore($newScore);
                    $em->flush();
                    return $this->json(['score' => $newScore, 'userVote' => $value]);
                }
            }

            // New vote
            $vote = new Vote();
            $vote->setUser($user);
            $vote->setBoycottProduct($boycott);
            $vote->setValue($value);
            $em->persist($vote);

            // Reputation: voter gets +1, submitter gets +1/-1
            $user->addReputation(1);
            if ($boycott->getSubmittedBy() !== $user) {
                $boycott->getSubmittedBy()->addReputation($value);
            }

            $em->flush();
            $newScore = $voteRepo->calculateBoycottScore($boycott);
            $boycott->setVoteScore($newScore);
            $em->flush();

            return $this->json(['score' => $newScore, 'userVote' => $value]);

        } elseif ($type === 'alternative') {
            $alternative = $altRepo->find($targetId);
            if (!$alternative) {
                return $this->json(['error' => 'Not found'], 404);
            }

            $existingVote = $voteRepo->findUserAlternativeVote($user, $alternative);

            if ($existingVote) {
                if ($existingVote->getValue() === $value) {
                    $em->remove($existingVote);
                    $user->addReputation(-1);
                    $em->flush();
                    $newScore = $voteRepo->calculateAlternativeScore($alternative);
                    $alternative->setVoteScore($newScore);
                    $em->flush();
                    return $this->json(['score' => $newScore, 'userVote' => 0]);
                } else {
                    $existingVote->setValue($value);
                    $em->flush();
                    $newScore = $voteRepo->calculateAlternativeScore($alternative);
                    $alternative->setVoteScore($newScore);
                    $em->flush();
                    return $this->json(['score' => $newScore, 'userVote' => $value]);
                }
            }

            $vote = new Vote();
            $vote->setUser($user);
            $vote->setAlternative($alternative);
            $vote->setValue($value);
            $em->persist($vote);

            $user->addReputation(1);
            if ($alternative->getSuggestedBy() !== $user) {
                $alternative->getSuggestedBy()->addReputation($value);
            }

            $em->flush();
            $newScore = $voteRepo->calculateAlternativeScore($alternative);
            $alternative->setVoteScore($newScore);
            $em->flush();

            return $this->json(['score' => $newScore, 'userVote' => $value]);
        }

        return $this->json(['error' => 'Invalid type'], 400);
    }

    // ═══════════════════════════════════════════
    //  ADMIN: Review pending submissions
    // ═══════════════════════════════════════════

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/pending', name: 'app_boycott_admin_pending', methods: ['GET'])]
    public function adminPending(BoycottProductRepository $repo, AlternativeRepository $altRepo): Response
    {
        return $this->render('boycott/admin_pending.html.twig', [
            'pending'             => $repo->findPending(),
            'pendingAlternatives' => $altRepo->findPending(),
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/{id}/approve', name: 'app_boycott_admin_approve', methods: ['POST'])]
    public function adminApprove(BoycottProduct $boycott, EntityManagerInterface $em, NotificationService $notifService): Response
    {
        $boycott->setStatus(BoycottProduct::STATUS_APPROVED);

        // Award submitter reputation for approved boycott
        $boycott->getSubmittedBy()->addReputation(5);

        $em->flush();

        // Notify submitter
        if ($boycott->getSubmittedBy()) {
            $notifService->create(
                $boycott->getSubmittedBy(),
                'Votre suggestion "' . $boycott->getName() . '" a été approuvée ! (+5 réputation)',
                'boycott_approved',
                null,
                'bi-check-circle-fill'
            );
        }

        $this->addFlash('success', 'Boycott "' . $boycott->getName() . '" approuvé.');
        return $this->redirectToRoute('app_boycott_admin_pending');
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/{id}/reject', name: 'app_boycott_admin_reject', methods: ['POST'])]
    public function adminReject(BoycottProduct $boycott, EntityManagerInterface $em, NotificationService $notifService): Response
    {
        $boycott->setStatus(BoycottProduct::STATUS_REJECTED);
        $em->flush();

        if ($boycott->getSubmittedBy()) {
            $notifService->create(
                $boycott->getSubmittedBy(),
                'Votre suggestion "' . $boycott->getName() . '" a été rejetée.',
                'boycott_rejected',
                null,
                'bi-x-circle-fill'
            );
        }

        $this->addFlash('warning', 'Boycott "' . $boycott->getName() . '" rejeté.');
        return $this->redirectToRoute('app_boycott_admin_pending');
    }

    // ═══════════════════════════════════════════
    //  SHOW alternative detail
    // ═══════════════════════════════════════════

    #[Route('/alternative/{id}', name: 'app_boycott_show_alternative', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function showAlternative(Alternative $alternative, VoteRepository $voteRepo): Response
    {
        // Non-admins can only see approved alternatives
        if (!$this->isGranted('ROLE_ADMIN') && !$alternative->isApproved()) {
            throw $this->createNotFoundException('Cette alternative n\'est pas disponible.');
        }

        $userVote = null;
        if ($this->getUser()) {
            $vote = $voteRepo->findUserAlternativeVote($this->getUser(), $alternative);
            $userVote = $vote?->getValue();
        }

        return $this->render('boycott/show_alternative.html.twig', [
            'alternative' => $alternative,
            'boycott'     => $alternative->getBoycottProduct(),
            'userVote'    => $userVote,
        ]);
    }

    // ═══════════════════════════════════════════
    //  ADMIN: Edit boycott product
    // ═══════════════════════════════════════════

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/{id}/edit', name: 'app_boycott_admin_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function adminEditBoycott(BoycottProduct $boycott, Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(BoycottProductType::class, $boycott);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
                try {
                    $imageFile->move($this->getParameter('boycott_directory'), $newFilename);
                    $boycott->setImage($newFilename);
                } catch (FileException $e) {}
            }
            $em->flush();
            $this->addFlash('success', 'Boycott "' . $boycott->getName() . '" mis à jour.');
            return $this->redirectToRoute('app_boycott_show', ['id' => $boycott->getId()]);
        }

        return $this->render('boycott/edit_boycott.html.twig', [
            'boycott' => $boycott,
            'form'    => $form,
        ]);
    }

    // ═══════════════════════════════════════════
    //  ADMIN: Edit alternative
    // ═══════════════════════════════════════════

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/alternative/{id}/edit', name: 'app_boycott_admin_edit_alternative', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function adminEditAlternative(Alternative $alternative, Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $currentProduct = $alternative->getProduct();
        $form = $this->createForm(AlternativeType::class, $alternative, [
            'show_for_sale'    => true,
            'for_sale_default' => $currentProduct !== null,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
                try {
                    $imageFile->move($this->getParameter('boycott_directory'), $newFilename);
                    $alternative->setImage($newFilename);
                } catch (FileException $e) {}
            }

            $forSale    = (bool) $form->get('forSale')->getData();
            $wasForSale = $currentProduct !== null;

            if ($forSale && !$wasForSale) {
                // Was info-only → now sellable: create a Product in the store
                $this->createProductFromAlternative($alternative, $em);
            } elseif (!$forSale && $wasForSale) {
                // Was sellable → now info-only: unlink (and delete if not in any order)
                $inOrders = $em->getRepository(\App\Entity\LigneCommande::class)
                    ->count(['product' => $currentProduct]);
                $alternative->setProduct(null);
                $alternative->setPrice(null);
                if ($inOrders === 0) {
                    $em->remove($currentProduct);
                }
            } elseif ($forSale && $wasForSale) {
                // Still sellable: sync product fields
                $p = $alternative->getProduct();
                $p->setLabel($alternative->getName() . ' (' . $alternative->getBrand() . ')');
                $p->setPrice($alternative->getPrice());
                if ($imageFile && $alternative->getImage()) {
                    $p->setImage($alternative->getImage());
                }
            }

            $em->flush();
            $this->addFlash('success', 'Alternative "' . $alternative->getName() . '" mise à jour.');
            return $this->redirectToRoute('app_boycott_show', ['id' => $alternative->getBoycottProduct()->getId()]);
        }

        return $this->render('boycott/edit_alternative.html.twig', [
            'alternative' => $alternative,
            'boycott'     => $alternative->getBoycottProduct(),
            'form'        => $form,
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/{id}/delete', name: 'app_boycott_admin_delete', methods: ['POST'])]
    public function adminDelete(BoycottProduct $boycott, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete-boycott-' . $boycott->getId(), $request->request->get('_token'))) {
            $em->remove($boycott);
            $em->flush();
            $this->addFlash('success', 'Boycott supprimé.');
        }
        return $this->redirectToRoute('app_boycott_admin_pending');
    }

    // ═══════════════════════════════════════════
    //  ADMIN: Alternative approve / reject
    // ═══════════════════════════════════════════

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/alternative/{id}/approve', name: 'app_boycott_admin_approve_alternative', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function adminApproveAlternative(Alternative $alternative, EntityManagerInterface $em, NotificationService $notifService): Response
    {
        $alternative->setStatus(\App\Entity\Alternative::STATUS_APPROVED);
        $this->createProductFromAlternative($alternative, $em);
        $em->flush();

        // Notify submitter
        if ($alternative->getSuggestedBy()) {
            $notifService->create(
                $alternative->getSuggestedBy(),
                'Votre alternative "' . $alternative->getName() . '" a été approuvée et ajoutée à la boutique !',
                'alt_approved',
                null,
                'bi-check-circle-fill'
            );
        }

        $this->addFlash('success', 'Alternative "' . $alternative->getName() . '" approuvée et ajoutée aux produits.');
        return $this->redirectToRoute('app_boycott_admin_pending');
    }

    // ───────────────────────────────────────────
    //  Private helper: create Product from approved Alternative
    // ───────────────────────────────────────────
    private function createProductFromAlternative(Alternative $alternative, EntityManagerInterface $em): void
    {
        $boycott = $alternative->getBoycottProduct();

        $product = new Product();
        $product->setLabel($alternative->getName() . ' (' . $alternative->getBrand() . ')');
        $product->setPrice($alternative->getPrice());
        $product->setQuantity(0);
        $product->setIsAlternative(true);
        $product->setAlternativeFor($boycott->getName() . ' — ' . $boycott->getBrand());

        if ($alternative->getImage()) {
            $product->setImage($alternative->getImage());
        }

        // Find or create "Alternatives" category
        $altCategory = $em->getRepository(\App\Entity\Category::class)->findOneBy(['name' => 'Alternatives']);
        if (!$altCategory) {
            $altCategory = new \App\Entity\Category();
            $altCategory->setName('Alternatives');
            $em->persist($altCategory);
        }
        $product->setCategory($altCategory);

        // Find or create Supplier from brand
        $supplier = $em->getRepository(\App\Entity\Supplier::class)->findOneBy(['name_supp' => $alternative->getBrand()]);
        if (!$supplier) {
            $supplier = new \App\Entity\Supplier();
            $supplier->setNameSupp($alternative->getBrand());
            $em->persist($supplier);
        }
        $product->setSupplierId($supplier);

        $em->persist($product);
        $alternative->setProduct($product);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/alternative/{id}/reject', name: 'app_boycott_admin_reject_alternative', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function adminRejectAlternative(Alternative $alternative, EntityManagerInterface $em, NotificationService $notifService): Response
    {
        $alternative->setStatus(\App\Entity\Alternative::STATUS_REJECTED);
        $em->flush();

        if ($alternative->getSuggestedBy()) {
            $notifService->create(
                $alternative->getSuggestedBy(),
                'Votre alternative "' . $alternative->getName() . '" a été rejetée.',
                'alt_rejected',
                null,
                'bi-x-circle-fill'
            );
        }

        $this->addFlash('warning', 'Alternative "' . $alternative->getName() . '" rejetée.');
        return $this->redirectToRoute('app_boycott_admin_pending');
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/alternative/{id}/delete', name: 'app_boycott_admin_delete_alternative', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function adminDeleteAlternative(Alternative $alternative, Request $request, EntityManagerInterface $em): Response
    {
        $boycottId = $alternative->getBoycottProduct()->getId();

        if ($this->isCsrfTokenValid('delete-alt-' . $alternative->getId(), $request->request->get('_token'))) {
            // Remove linked product from store if it exists
            if ($alternative->getProduct()) {
                $em->remove($alternative->getProduct());
            }
            $em->remove($alternative);
            $em->flush();
            $this->addFlash('success', 'Alternative supprimée.');
        }

        // Redirect back to admin_pending if that's where the action came from, else show page
        $referer = $request->headers->get('referer', '');
        if (str_contains($referer, 'admin/pending')) {
            return $this->redirectToRoute('app_boycott_admin_pending');
        }
        return $this->redirectToRoute('app_boycott_show', ['id' => $boycottId]);
    }

    // ═══════════════════════════════════════════
    //  ADMIN: Set stock quantity for alternative product
    // ═══════════════════════════════════════════

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/alternative/{id}/stock', name: 'app_boycott_admin_set_stock', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function adminSetStock(Alternative $alternative, Request $request, EntityManagerInterface $em): Response
    {
        $quantity = (int) $request->request->get('quantity', 0);
        $product = $alternative->getProduct();

        if ($product) {
            $product->setQuantity(max(0, $quantity));
            $em->flush();
            $this->addFlash('success', 'Stock de "' . $alternative->getName() . '" mis à jour : ' . $quantity . ' unité(s).');
        } else {
            $this->addFlash('warning', 'Aucun produit lié à cette alternative.');
        }

        return $this->redirectToRoute('app_boycott_show', ['id' => $alternative->getBoycottProduct()->getId()]);
    }

    // ═══════════════════════════════════════════
    //  LEADERBOARD (community reputation)
    // ═══════════════════════════════════════════

    #[Route('/community/leaderboard', name: 'app_boycott_leaderboard', methods: ['GET'])]
    public function leaderboard(EntityManagerInterface $em): Response
    {
        $users = $em->getRepository(\App\Entity\User::class)
            ->createQueryBuilder('u')
            ->where('u.reputation > 0')
            ->andWhere('u.roles NOT LIKE :adminRole')
            ->setParameter('adminRole', '%ROLE_ADMIN%')
            ->orderBy('u.reputation', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();

        return $this->render('boycott/leaderboard.html.twig', [
            'topUsers' => $users,
        ]);
    }
}
