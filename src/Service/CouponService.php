<?php

namespace App\Service;

use App\Entity\Coupon;
use App\Entity\User;
use App\Repository\CouponRepository;
use Doctrine\ORM\EntityManagerInterface;

class CouponService
{
    public function __construct(
        private CouponRepository $couponRepository,
        private EntityManagerInterface $entityManager,
    ) {}

    // -------------------------------------------------------------------------
    // PUBLIC API
    // -------------------------------------------------------------------------

    /**
     * Award reputation points earned after a purchase, then check if any
     * 200-pt milestones were crossed. Creates one 50% coupon per milestone.
     *
     * @return Coupon[] array of new coupons (empty if no milestone crossed)
     */
    public function awardPointsAndCheckMilestone(User $user, int $points): array
    {
        if ($points <= 0) {
            return [];
        }

        $oldReputation = $user->getReputation();
        $newReputation = $oldReputation + $points;
        $user->setReputation($newReputation);

        $milestonesBefore = intdiv($oldReputation, Coupon::MILESTONE);
        $milestonesAfter  = intdiv($newReputation, Coupon::MILESTONE);

        $newMilestones = $milestonesAfter - $milestonesBefore;
        $newCoupons = [];

        for ($i = 0; $i < $newMilestones; $i++) {
            $newCoupons[] = $this->createCoupon($user, Coupon::MILESTONE_RATE, null);
        }

        return $newCoupons;
    }

    /**
     * Create the welcome coupon (20% off Hygiene category) for a new user.
     */
    public function createWelcomeCoupon(User $user): Coupon
    {
        return $this->createCoupon($user, Coupon::WELCOME_RATE, Coupon::HYGIENE_CATEGORY);
    }

    /**
     * Validate a coupon code for a given user.
     * Returns the Coupon if valid, unused, and belongs to the user. Null otherwise.
     */
    public function validateCode(string $code, User $user): ?Coupon
    {
        $coupon = $this->couponRepository->findByCode(strtoupper(trim($code)));

        if (!$coupon || $coupon->isUsed() || $coupon->getUser() !== $user) {
            return null;
        }

        return $coupon;
    }

    /**
     * Mark a coupon as used and flush immediately.
     */
    public function markAsUsed(Coupon $coupon): void
    {
        $coupon->setIsUsed(true);
        $this->entityManager->flush();
    }

    /**
     * Get all unused coupons for a user (most recent first).
     *
     * @return Coupon[]
     */
    public function getUnusedCoupons(User $user): array
    {
        return $this->couponRepository->findUnusedByUser($user);
    }

    /**
     * Convert reputation points to wallet credit.
     * Rate: 100 pts = 10 DT.
     * Only multiples of 100 are accepted. Returns the DT amount credited, or 0 if insufficient.
     */
    public function convertPointsToWallet(User $user, int $pointsToConvert): float
    {
        $blocks = intdiv($pointsToConvert, 100);
        if ($blocks <= 0) {
            return 0.0;
        }

        $actualPoints = $blocks * 100;
        if ($user->getReputation() < $actualPoints) {
            return 0.0;
        }

        $credit = $blocks * 10.0;
        $user->setReputation($user->getReputation() - $actualPoints);
        $user->addWalletBalance($credit);
        $this->entityManager->flush();

        return $credit;
    }

    /**
     * Get the best coupon to display in the cart:
     * Full-cart coupons (no category restriction) take priority.
     */
    public function getBestCoupon(User $user): ?Coupon
    {
        $coupons = $this->getUnusedCoupons($user);
        if (empty($coupons)) {
            return null;
        }

        foreach ($coupons as $c) {
            if ($c->getCategoryName() === null) {
                return $c;
            }
        }

        return $coupons[0];
    }

    // -------------------------------------------------------------------------
    // PRIVATE HELPERS
    // -------------------------------------------------------------------------

    private function createCoupon(User $user, float $rate, ?string $categoryName): Coupon
    {
        $coupon = new Coupon();
        $coupon->setUser($user);
        $coupon->setCode($this->generateCode());
        $coupon->setDiscountRate($rate);
        $coupon->setCategoryName($categoryName);

        $this->entityManager->persist($coupon);
        $this->entityManager->flush();

        return $coupon;
    }

    private function generateCode(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code  = 'ALT-';
        for ($i = 0; $i < 8; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }

        if ($this->couponRepository->findByCode($code)) {
            return $this->generateCode();
        }

        return $code;
    }
}
