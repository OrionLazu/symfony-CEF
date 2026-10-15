<?php

namespace App\Repository;

use App\Entity\Product;
use App\Model\PriceRange;
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
     * Sweat-shirts mis en avant sur la page d'accueil.
     *
     * @return list<Product>
     */
    public function findFeatured(int $limit = 3): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.featured = true')
            ->orderBy('p.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Liste de la boutique, éventuellement filtrée par fourchette de prix.
     *
     * @return list<Product>
     */
    public function findByPriceRange(?PriceRange $range): array
    {
        $qb = $this->createQueryBuilder('p')->orderBy('p.id', 'ASC');

        if (null !== $range) {
            $qb->andWhere('p.price >= :min')->setParameter('min', $range->min);
            // La borne haute est exclue, sauf pour la dernière fourchette.
            $qb->andWhere($range->includeMax ? 'p.price <= :max' : 'p.price < :max')
                ->setParameter('max', $range->max);
        }

        return $qb->getQuery()->getResult();
    }
}
