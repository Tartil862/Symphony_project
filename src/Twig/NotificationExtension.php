<?php

namespace App\Twig;

use App\Repository\NotificationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Twig extension that injects notification data (unread count + recent)
 * into every template as global variables.
 */
class NotificationExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private Security $security,
    ) {}

    public function getGlobals(): array
    {
        $user = $this->security->getUser();

        if (!$user) {
            return [
                'notif_unread_count' => 0,
                'notif_recent' => [],
            ];
        }

        return [
            'notif_unread_count' => $this->notificationRepository->countUnread($user),
            'notif_recent' => $this->notificationRepository->findRecent($user, 5),
        ];
    }
}
