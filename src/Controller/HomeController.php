<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(Request $request, ProductRepository $productRepository, CategoryRepository $categoryRepository): Response
    {
        // Authenticated users are redirected to their role-appropriate area
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_product_index');
        }

        if ($this->isGranted('ROLE_USER')) {
            return $this->redirectToRoute('app_dashboard');
        }

        // Guest: render the public product catalog
        $search     = $request->query->get('q');
        $categoryId = $request->query->getInt('category') ?: null;
        $stock      = $request->query->get('stock');

        $products = $productRepository->searchAndFilter($search, $categoryId, null, $stock);

        return $this->render('dashboard/index.html.twig', [
            'products'        => $products,
            'categories'      => $categoryRepository->findAll(),
            'currentSearch'   => $search,
            'currentCategory' => $categoryId,
            'currentStock'    => $stock,
            'userCoupon'      => null,
            'isGuest'         => true,
        ]);
    }
}
