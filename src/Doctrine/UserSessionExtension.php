<?php

// src/Doctrine/UserSessionExtension.php
// Extension Doctrine pour filtrer automatiquement les sessions par utilisateur.
//
// Problème résolu :
//   Sans ce filtre, GET /api/user_sessions retournait TOUTES les sessions
//   de tous les utilisateurs à n'importe quel utilisateur connecté.
//   C'est un problème de confidentialité : un utilisateur ne doit voir
//   que SES propres sessions.
//
// Solution :
//   Cette extension ajoute automatiquement WHERE user = :utilisateurConnecte
//   sur toutes les requêtes UserSession, SAUF pour les administrateurs
//   qui peuvent voir toutes les sessions (pour les statistiques).

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\UserSession;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

class UserSessionExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(
        // Le service Security permet de récupérer l'utilisateur connecté
        private readonly Security $security,
    ) {
    }

    /**
     * Appliqué sur les collections : GET /api/user_sessions
     * Les non-admins ne voient que leurs propres sessions.
     */
    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        Operation $operation = null,
        array $context = []
    ): void {
        $this->filtrerSiNecessaire($queryBuilder, $resourceClass);
    }

    /**
     * Appliqué sur les items : GET /api/user_sessions/{id}
     * La sécurité de l'opération Get gère déjà ce cas (object.getUser() == user),
     * mais on ajoute le filtre par cohérence.
     */
    public function applyToItem(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        array $identifiers,
        Operation $operation = null,
        array $context = []
    ): void {
        $this->filtrerSiNecessaire($queryBuilder, $resourceClass);
    }

    /**
     * Ajoute WHERE user = :utilisateurConnecte si :
     * - la ressource est une UserSession
     * - l'utilisateur connecté n'est pas admin
     */
    private function filtrerSiNecessaire(QueryBuilder $queryBuilder, string $resourceClass): void
    {
        // N'intervient que sur les UserSession
        if ($resourceClass !== UserSession::class) {
            return;
        }

        // Les admins voient toutes les sessions (utile pour les statistiques)
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        // Récupère l'utilisateur actuellement connecté
        $utilisateurConnecte = $this->security->getUser();

        // Si pas d'utilisateur connecté (ne devrait pas arriver vu la sécurité),
        // on retourne un résultat vide par sécurité
        if ($utilisateurConnecte === null) {
            $alias = $queryBuilder->getRootAliases()[0];
            $queryBuilder->andWhere("$alias.id IS NULL");
            return;
        }

        // Filtre les sessions de l'utilisateur connecté uniquement
        $alias = $queryBuilder->getRootAliases()[0];
        $queryBuilder
            ->andWhere("$alias.user = :utilisateurConnecte")
            ->setParameter('utilisateurConnecte', $utilisateurConnecte);
    }
}
