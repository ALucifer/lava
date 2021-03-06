<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Retourne la liste des secretaires.
     *
     * @return mixed
     */
    public function findSecretaires()
    {
        return $this->createQueryBuilder('u')
            ->where('u.roles LIKE :secretaire')
            ->setParameter('secretaire', '%ROLE_SECRETARY%')
            ->getQuery()
            ->getResult();
    }

    public function findLast5User()
    {
        return $this->createQueryBuilder('u')
            ->orderBy('u.id', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
    }

    public function findLast5BlockedUser()
    {
        return $this->createQueryBuilder('u')
            ->where('u.isBlocked = 1')
            ->orderBy('u.id', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
    }
}
