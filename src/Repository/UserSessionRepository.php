<?php

// src/Repository/UserSessionRepository.php
// Dépôt Doctrine pour l'entité UserSession.
// Le filtrage par utilisateur est géré automatiquement par UserSessionExtension —
// aucune méthode manuelle n'est nécessaire.

namespace App\Repository;

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
}
