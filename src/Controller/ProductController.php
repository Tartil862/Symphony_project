<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProductController extends AbstractController
{
    #[Route('/hello', name: 'hello_ingta')]
public function hello(): Response
{
    return new Response('Hello from INGTA B');
}

}
