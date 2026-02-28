<?php

namespace App\Repository;

use App\Entity\BoycottProduct;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BoycottProduct>
 */
class BoycottProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BoycottProduct::class);
    }

    /**
     * Search & filter boycott products.
     */
    public function searchAndFilter(?string $query = null, ?string $reason = null, ?string $level = null, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->orderBy('b.voteScore', 'DESC')
            ->addOrderBy('b.createdAt', 'DESC');

        if ($query) {
            $qb->andWhere('b.name LIKE :q OR b.brand LIKE :q OR b.description LIKE :q')
               ->setParameter('q', '%' . $query . '%');
        }

        if ($reason) {
            $qb->andWhere('b.reason = :reason')
               ->setParameter('reason', $reason);
        }

        if ($level) {
            $qb->andWhere('b.boycottLevel = :level')
               ->setParameter('level', $level);
        }

        if ($status) {
            $qb->andWhere('b.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get only approved boycott products.
     */
    public function findApproved(?string $query = null, ?string $reason = null, ?string $level = null): array
    {
        return $this->searchAndFilter($query, $reason, $level, BoycottProduct::STATUS_APPROVED);
    }

    /**
     * Get pending submissions for admin review.
     */
    public function findPending(): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.status = :status')
            ->setParameter('status', BoycottProduct::STATUS_PENDING)
            ->orderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count by status.
     */
    public function countByStatus(string $status): int
    {
        return $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Top boycotted products (by vote score).
     */
    public function findTopBoycotted(int $limit = 5): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.status = :status')
            ->setParameter('status', BoycottProduct::STATUS_APPROVED)
            ->orderBy('b.voteScore', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
