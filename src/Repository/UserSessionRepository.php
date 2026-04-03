<?php

// src/Repository/UserSessionRepository.php
// Dépôt Doctrine pour l'entité UserSession.
// Contient les méthodes de recherche pour l'historique des sessions utilisateur.

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserSession>
 */
class UserSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserSession::class);
    }

    // Retourne toutes les sessions d'un utilisateur, de la plus récente à la plus ancienne
    public function trouverParUtilisateur(User $utilisateur): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.user = :user')
            ->setParameter('user', $utilisateur)
            ->orderBy('s.startedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // Retourne les sessions complétées d'un utilisateur
    public function trouverSessionsCompletees(User $utilisateur): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.user = :user')
            ->andWhere('s.status = :status')
            ->setParameter('user', $utilisateur)
            ->setParameter('status', UserSession::STATUS_COMPLETED)
            ->orderBy('s.startedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
