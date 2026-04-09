<?php

// src/Doctrine/UserSessionExtension.php
// Extension Doctrine pour filtrer automatiquement les sessions par utilisateur.
//
// Problème résolu :
//   Sans ce filtre, GET /api/user_sessions retourne les sessions de TOUS
//   les utilisateurs à n'importe quel utilisateur connecté. Un utilisateur
//   pouvait donc voir l'historique d'exercices d'autres personnes.
//
// Solution :
//   Cette extension injecte automatiquement un filtre WHERE s.user = :moi
//   sur toutes les requêtes de collection et d'item UserSession,
//   sauf pour les administrateurs qui peuvent voir tout l'historique.

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\User;
use App\Entity\UserSession;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

class UserSessionExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(
        // Le service Security donne accès à l'utilisateur connecté et à ses rôles
        private readonly Security $security,
    ) {
    }

    /**
     * Appliqué sur les requêtes de collection : GET /api/user_sessions
     * Chaque utilisateur ne voit que ses propres sessions.
     */
    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []
    ): void {
        $this->filtrerParUtilisateur($queryBuilder, $resourceClass);
    }

    /**
     * Appliqué sur les requêtes d'item : GET /api/user_sessions/{id}
     * Un utilisateur ne peut pas accéder à la session d'un autre par son id.
     * (La sécurité déclarative sur l'entité couvre déjà ce cas, mais on
     * double la protection au niveau Doctrine pour cohérence.)
     */
    public function applyToItem(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        array $identifiers,
        ?Operation $operation = null,
        array $context = []
    ): void {
        $this->filtrerParUtilisateur($queryBuilder, $resourceClass);
    }

    /**
     * Ajoute le filtre WHERE s.user = :moi si :
     * - la ressource est une UserSession
     * - l'utilisateur n'est pas admin
     * - un utilisateur est bien connecté
     */
    private function filtrerParUtilisateur(QueryBuilder $queryBuilder, string $resourceClass): void
    {
        // N'intervient que sur les UserSessions
        if ($resourceClass !== UserSession::class) {
            return;
        }

        // Les admins voient toutes les sessions (pour analytics et support)
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        // Récupère l'utilisateur connecté
        $utilisateur = $this->security->getUser();

        // Si personne n'est connecté, on ne retourne rien (sécurité défensive)
        if (!$utilisateur instanceof User) {
            $alias = $queryBuilder->getRootAliases()[0];
            $queryBuilder->andWhere("$alias.id IS NULL");
            return;
        }

        // Filtre : uniquement les sessions appartenant à l'utilisateur connecté
        $alias = $queryBuilder->getRootAliases()[0];
        $queryBuilder
            ->andWhere("$alias.user = :utilisateurConnecte")
            ->setParameter('utilisateurConnecte', $utilisateur);
    }
}
