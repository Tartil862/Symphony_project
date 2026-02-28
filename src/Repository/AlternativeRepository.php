<?php

namespace App\Repository;

use App\Entity\Alternative;
use App\Entity\BoycottProduct;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Alternative>
 */
class AlternativeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Alternative::class);
    }

    /**
     * Find alternatives for a specific boycott product, ordered by vote score.
     */
    public function findForBoycott(BoycottProduct $boycottProduct): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.boycottProduct = :bp')
            ->setParameter('bp', $boycottProduct)
            ->orderBy('a.voteScore', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Top-rated alternatives overall.
     */
    public function findTopAlternatives(int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->join('a.boycottProduct', 'bp')
            ->where('bp.status = :status')
            ->setParameter('status', BoycottProduct::STATUS_APPROVED)
            ->orderBy('a.voteScore', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Pending alternatives waiting for admin review.
     */
    public function findPending(): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.status = :status')
            ->setParameter('status', \App\Entity\Alternative::STATUS_PENDING)
            ->orderBy('a.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
