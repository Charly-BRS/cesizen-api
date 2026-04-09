<?php

// src/Repository/UserRepository.php
// Dépôt Doctrine pour l'entité User.
// Contient les méthodes de recherche personnalisées pour les utilisateurs.

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    // Méthode requise par PasswordUpgraderInterface
    // Symfony l'appelle automatiquement pour mettre à jour les hashs obsolètes
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Les instances de "%s" ne sont pas supportées.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    // Retourne les comptes désactivés dont la dateDesactivation est antérieure à $dateLimite.
    // Utilisé par la commande app:supprimer-comptes-expires pour la conformité RGPD.
    public function trouverComptesASupprimer(\DateTimeImmutable $dateLimite): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.isActif = :inactif')
            ->andWhere('u.dateDesactivation IS NOT NULL')
            ->andWhere('u.dateDesactivation <= :dateLimite')
            ->setParameter('inactif', false)
            ->setParameter('dateLimite', $dateLimite)
            ->getQuery()
            ->getResult();
    }
}
