<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Service\CouponService;
use App\Service\NotificationService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Form\FormError;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authenticator\FormLoginAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Contracts\Translation\TranslatorInterface;

final class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
        NotificationService $notificationService,
        CouponService $couponService,
        MailerInterface $mailer,
        TranslatorInterface $translator,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

            // Email verification token
            $token = bin2hex(random_bytes(32));
            $user->setEmailVerificationToken($token);
            $user->setIsVerified(false);

            $entityManager->persist($user);
            try {
                $entityManager->flush();
            } catch (UniqueConstraintViolationException) {
                $form->get('email')->addError(new FormError($translator->trans('auth.email_already_used')));
                return $this->render('security/register.html.twig', ['registrationForm' => $form]);
            }

            // Build absolute verification URL
            $verifyUrl = $this->generateUrl(
                'app_verify_email',
                ['token' => $token],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            // Send verification email
            $email = (new TemplatedEmail())
                ->from("L'Alternative <tbenfriha309@gmail.com>")
                ->to($user->getEmail())
                ->subject("Vérifiez votre adresse email – L'Alternative")
                ->htmlTemplate('security/verification_email.html.twig')
                ->context(['user' => $user, 'verifyUrl' => $verifyUrl]);
            $mailer->send($email);

            // Welcome coupon (will be usable after verification)
            $welcomeCoupon = $couponService->createWelcomeCoupon($user);

            // Notifications (stored, user will see them after verifying + logging in)
            $notificationService->create(
                $user,
                sprintf("Bienvenue sur L'Alternative, %s ! 🌿 Découvrez des alternatives locales aux produits boycottés.", $user->getPrenom() ?? $user->getEmail()),
                'welcome',
                null,
                'bi-stars'
            );
            $notificationService->create(
                $user,
                sprintf('🎁 Coupon de bienvenue : 20%% de réduction sur les produits Hygiène ! Code : %s', $welcomeCoupon->getCode()),
                'coupon',
                $this->generateUrl('app_cart_index'),
                'bi-ticket-perforated-fill'
            );
            $notificationService->notifyUsersWithRole(
                sprintf('Nouvel utilisateur inscrit : %s (%s)', $user->getFullName(), $user->getEmail()),
                'info',
                null,
                'bi-person-plus',
                ['ROLE_ADMIN']
            );

            return $this->redirectToRoute('app_verify_pending', ['email' => $user->getEmail()]);
        }

        return $this->render('security/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    #[Route('/verify-pending', name: 'app_verify_pending')]
    public function verifyPending(Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('security/verify_pending.html.twig', [
            'email' => $request->query->get('email', ''),
        ]);
    }

    #[Route('/verify/{token}', name: 'app_verify_email')]
    public function verifyEmail(string $token, UserRepository $userRepository, EntityManagerInterface $em, Security $security): Response
    {
        $user = $userRepository->findOneBy(['emailVerificationToken' => $token]);

        if (!$user) {
            $this->addFlash('error', 'Lien de vérification invalide ou expiré.');
            return $this->redirectToRoute('app_login');
        }

        $user->setIsVerified(true);
        $user->setEmailVerificationToken(null);
        $em->flush();

        // Auto-login with remember me — redirect straight to dashboard
        $response = $security->login($user, FormLoginAuthenticator::class, 'main', [new RememberMeBadge()]);
        return $response ?? $this->redirectToRoute('app_dashboard');
    }
}
