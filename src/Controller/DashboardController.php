<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Service\CouponService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(Request $request, ProductRepository $productRepository, CategoryRepository $categoryRepository, CouponService $couponService): Response
    {
        $search = $request->query->get('q');
        $categoryId = $request->query->getInt('category') ?: null;
        $stock = $request->query->get('stock');

        $products = $productRepository->searchAndFilter($search, $categoryId, null, $stock);

        $userCoupon = $this->getUser() ? $couponService->getBestCoupon($this->getUser()) : null;

        return $this->render('dashboard/index.html.twig', [
            'products'        => $products,
            'categories'      => $categoryRepository->findAll(),
            'currentSearch'   => $search,
            'currentCategory' => $categoryId,
            'currentStock'    => $stock,
            'userCoupon'      => $userCoupon,
        ]);
    }
}
