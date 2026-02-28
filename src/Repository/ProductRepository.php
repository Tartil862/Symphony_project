<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * @return Product[]
     */
    public function searchAndFilter(?string $search = null, ?int $categoryId = null, ?int $supplierId = null, ?string $stock = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->leftJoin('p.supplier_id', 's');

        if ($search) {
            $qb->andWhere('p.label LIKE :search OR c.name LIKE :search OR s.name_supp LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($categoryId) {
            $qb->andWhere('c.id = :catId')
               ->setParameter('catId', $categoryId);
        }

        if ($supplierId) {
            $qb->andWhere('s.id = :supId')
               ->setParameter('supId', $supplierId);
        }

        if ($stock === 'in') {
            $qb->andWhere('p.quantity > 0');
        } elseif ($stock === 'out') {
            $qb->andWhere('p.quantity <= 0');
        } elseif ($stock === 'low') {
            $qb->andWhere('p.quantity > 0 AND p.quantity <= 5');
        }

        return $qb->orderBy('p.id', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    //    /**
    //     * @return Product[] Returns an array of Product objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Product
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
