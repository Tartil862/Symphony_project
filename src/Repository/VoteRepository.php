<?php

namespace App\Repository;

use App\Entity\Alternative;
use App\Entity\BoycottProduct;
use App\Entity\User;
use App\Entity\Vote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Vote>
 */
class VoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vote::class);
    }

    /**
     * Find user's vote on a boycott product.
     */
    public function findUserBoycottVote(User $user, BoycottProduct $boycottProduct): ?Vote
    {
        return $this->createQueryBuilder('v')
            ->where('v.user = :user')
            ->andWhere('v.boycottProduct = :bp')
            ->setParameter('user', $user)
            ->setParameter('bp', $boycottProduct)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find user's vote on an alternative.
     */
    public function findUserAlternativeVote(User $user, Alternative $alternative): ?Vote
    {
        return $this->createQueryBuilder('v')
            ->where('v.user = :user')
            ->andWhere('v.alternative = :alt')
            ->setParameter('user', $user)
            ->setParameter('alt', $alternative)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Calculate total score for a boycott product.
     */
    public function calculateBoycottScore(BoycottProduct $boycottProduct): int
    {
        $result = $this->createQueryBuilder('v')
            ->select('COALESCE(SUM(v.value), 0)')
            ->where('v.boycottProduct = :bp')
            ->setParameter('bp', $boycottProduct)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result;
    }

    /**
     * Calculate total score for an alternative.
     */
    public function calculateAlternativeScore(Alternative $alternative): int
    {
        $result = $this->createQueryBuilder('v')
            ->select('COALESCE(SUM(v.value), 0)')
            ->where('v.alternative = :alt')
            ->setParameter('alt', $alternative)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result;
    }

    /**
     * Get all user's votes on boycott products (bulk load for display).
     */
    public function findUserBoycottVotes(User $user): array
    {
        $votes = $this->createQueryBuilder('v')
            ->where('v.user = :user')
            ->andWhere('v.boycottProduct IS NOT NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($votes as $vote) {
            $map[$vote->getBoycottProduct()->getId()] = $vote->getValue();
        }
        return $map;
    }

    /**
     * Get all user's votes on alternatives for a specific boycott (bulk load).
     */
    public function findUserAlternativeVotesForBoycott(User $user, BoycottProduct $boycottProduct): array
    {
        $votes = $this->createQueryBuilder('v')
            ->join('v.alternative', 'a')
            ->where('v.user = :user')
            ->andWhere('a.boycottProduct = :bp')
            ->setParameter('user', $user)
            ->setParameter('bp', $boycottProduct)
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($votes as $vote) {
            $map[$vote->getAlternative()->getId()] = $vote->getValue();
        }
        return $map;
    }
}
