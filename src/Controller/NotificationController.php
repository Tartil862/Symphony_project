<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/notification')]
final class NotificationController extends AbstractController
{
    #[Route('/', name: 'app_notification_index')]
    public function index(NotificationRepository $notificationRepository): Response
    {
        $notifications = $notificationRepository->findAllForUser($this->getUser());

        return $this->render('notification/index.html.twig', [
            'notifications' => $notifications,
        ]);
    }

    #[Route('/read/{id}', name: 'app_notification_read')]
    public function markRead(string $id, NotificationRepository $notificationRepository, EntityManagerInterface $em): Response
    {
        $notification = $notificationRepository->find((int) $id);

        if (!$notification || $notification->getUser() !== $this->getUser()) {
            return $this->redirectToRoute('app_notification_index');
        }

        $notification->setIsRead(true);
        $em->flush();

        if ($notification->getLink()) {
            return $this->redirect($notification->getLink());
        }

        return $this->redirectToRoute('app_notification_index');
    }

    #[Route('/read-all', name: 'app_notification_read_all')]
    public function markAllRead(NotificationRepository $notificationRepository, EntityManagerInterface $em): Response
    {
        $notificationRepository->markAllAsRead($this->getUser());

        $this->addFlash('success', 'Toutes les notifications ont été marquées comme lues.');
        return $this->redirectToRoute('app_notification_index');
    }

    #[Route('/delete/{id}', name: 'app_notification_delete', methods: ['POST'])]
    public function delete(string $id, NotificationRepository $notificationRepository, EntityManagerInterface $em): Response
    {
        $notification = $notificationRepository->find((int) $id);

        if (!$notification || $notification->getUser() !== $this->getUser()) {
            return $this->redirectToRoute('app_notification_index');
        }

        $em->remove($notification);
        $em->flush();

        $this->addFlash('success', 'Notification supprimée.');
        return $this->redirectToRoute('app_notification_index');
    }

    #[Route('/delete-all', name: 'app_notification_delete_all', methods: ['POST'])]
    public function deleteAll(NotificationRepository $notificationRepository, EntityManagerInterface $em): Response
    {
        $notifications = $notificationRepository->findAllForUser($this->getUser());

        foreach ($notifications as $notification) {
            $em->remove($notification);
        }
        $em->flush();

        $this->addFlash('success', 'Toutes les notifications ont été supprimées.');
        return $this->redirectToRoute('app_notification_index');
    }

    /**
     * AJAX endpoint for the bell dropdown — returns recent notifications as JSON.
     */
    #[Route('/api/recent', name: 'app_notification_api_recent')]
    public function apiRecent(NotificationRepository $notificationRepository): JsonResponse
    {
        $recent = $notificationRepository->findRecent($this->getUser(), 6);
        $unreadCount = $notificationRepository->countUnread($this->getUser());

        $data = [];
        foreach ($recent as $notif) {
            $data[] = [
                'id' => $notif->getId(),
                'message' => $notif->getMessage(),
                'type' => $notif->getType(),
                'icon' => $notif->getDisplayIcon(),
                'color' => $notif->getTypeColor(),
                'isRead' => $notif->isRead(),
                'timeAgo' => $notif->getTimeAgo(),
                'link' => $notif->getLink(),
                'readUrl' => $this->generateUrl('app_notification_read', ['id' => $notif->getId()]),
            ];
        }

        return new JsonResponse([
            'unreadCount' => $unreadCount,
            'notifications' => $data,
        ]);
    }
}
