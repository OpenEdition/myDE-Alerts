<?php

namespace MyDigitalEnvironment\AlertsBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Persistence\ManagerRegistry;
use MyDigitalEnvironment\AlertsBundle\Entity\Search;
use MyDigitalEnvironment\MyDigitalEnvironmentBundle\Entity\User;

/**
 * @extends ServiceEntityRepository<Search>
 */
class SearchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Search::class);
    }

    /** @return Search[] */
    public function findAllByUser(User $user): array
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(
        /** @lang DQL */ 'SELECT s
            FROM MyDigitalEnvironment\AlertsBundle\Entity\Search s
            WHERE s.subscriber = :user'
        )->setParameter('user', $user);

        return $query->getResult();
    }

    /** @return Search[] */
    public function findSynchronizedBy(array $criteria = []): array
    {
        // todo: test how long the query take with many (100+) search entities
        $qb = $this->createQueryBuilder('s');
        $qb->where($qb->expr()->orX(
            's.lastQueryDate is null',
            'DATE_DIFF(CURRENT_DATE(), s.lastQueryDate) >= s.frequency',
        ));
        return $qb
            ->getQuery()
            ->getResult();
    }

    /** @param int[] $ids
     * @return Search[]
     */
    public function findAllByIds(array $ids): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.id in (:ids)')
            ->setParameter('ids', $ids, ArrayParameterType::INTEGER)
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return Search[] Returns an array of Search objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('s.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Search
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
