<?php

namespace App\Controller;

use App\Entity\Supplier;
use App\Form\SupplierType;
use App\Repository\SupplierRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/supplier')]
final class SupplierController extends AbstractController
{
    #[Route(name: 'app_supplier_index', methods: ['GET'])]
    public function index(Request $request, SupplierRepository $supplierRepository): Response
    {
        $search = $request->query->get('q');
        $suppliers = $supplierRepository->search($search);

        return $this->render('supplier/index.html.twig', [
            'suppliers' => $suppliers,
            'currentSearch' => $search,
        ]);
    }

    #[Route('/new', name: 'app_supplier_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $supplier = new Supplier();
        $form = $this->createForm(SupplierType::class, $supplier);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($supplier);
            $entityManager->flush();

            return $this->redirectToRoute('app_supplier_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('supplier/new.html.twig', [
            'supplier' => $supplier,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_supplier_show', methods: ['GET'])]
    public function show(Supplier $supplier): Response
    {
        return $this->render('supplier/show.html.twig', [
            'supplier' => $supplier,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_supplier_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Supplier $supplier, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(SupplierType::class, $supplier);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_supplier_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('supplier/edit.html.twig', [
            'supplier' => $supplier,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_supplier_delete', methods: ['POST'])]
    public function delete(Request $request, Supplier $supplier, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$supplier->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($supplier);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_supplier_index', [], Response::HTTP_SEE_OTHER);
    }
}
