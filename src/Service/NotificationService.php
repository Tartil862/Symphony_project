<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service to create notifications easily from any controller/service.
 */
class NotificationService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function create(
        User $user,
        string $message,
        string $type = Notification::TYPE_INFO,
        ?string $link = null,
        ?string $icon = null,
    ): Notification {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setMessage($message);
        $notification->setType($type);
        $notification->setLink($link);
        $notification->setIcon($icon);

        $this->em->persist($notification);
        $this->em->flush();

        return $notification;
    }

    /**
     * Send a notification to all users with a given role.
     *
     * @param string[] $roles
     */
    public function notifyUsersWithRole(
        string $message,
        string $type = Notification::TYPE_INFO,
        ?string $link = null,
        ?string $icon = null,
        array $roles = ['ROLE_ADMIN'],
    ): void {
        $users = $this->em->getRepository(User::class)->findAll();

        foreach ($users as $user) {
            foreach ($roles as $role) {
                if (in_array($role, $user->getRoles())) {
                    $notification = new Notification();
                    $notification->setUser($user);
                    $notification->setMessage($message);
                    $notification->setType($type);
                    $notification->setLink($link);
                    $notification->setIcon($icon);
                    $this->em->persist($notification);
                    break;
                }
            }
        }

        $this->em->flush();
    }
}
