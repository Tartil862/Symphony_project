<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LocaleController extends AbstractController
{
    #[Route('/locale/{locale}', name: 'app_switch_locale', requirements: ['locale' => 'fr|en'])]
    public function switchLocale(string $locale, Request $request): Response
    {
        $request->getSession()->set('_locale', $locale);

        $referer = $request->headers->get('referer', $this->generateUrl('app_dashboard'));

        return $this->redirect($referer);
    }
}
