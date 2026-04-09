<?php

// src/Doctrine/ExerciceActifExtension.php
// Extension Doctrine pour filtrer automatiquement les exercices de respiration
// selon leur statut d'activation.
//
// Problème résolu :
//   L'API retournait TOUS les exercices (actifs + inactifs) à tous les
//   utilisateurs, même quand un admin les désactivait depuis le panneau mobile.
//   Les utilisateurs voyaient donc des exercices supposément masqués.
//
// Solution :
//   Cette extension s'accroche au QueryBuilder d'API Platform et ajoute
//   automatiquement un filtre WHERE isActive = true pour les collections
//   et les items, SAUF pour les administrateurs qui voient tout.

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\BreathingExercise;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

class ExerciceActifExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(
        // Le service Security permet de vérifier le rôle de l'utilisateur connecté
        private readonly Security $security,
    ) {
    }

    /**
     * Appliqué sur les requêtes de collection : GET /api/breathing_exercises
     * Les non-admins ne voient que les exercices actifs.
     */
    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []
    ): void {
        $this->filtrerSiNecessaire($queryBuilder, $resourceClass);
    }

    /**
     * Appliqué sur les requêtes d'item : GET /api/breathing_exercises/{id}
     * Un non-admin ne peut pas accéder à un exercice inactif par son id.
     */
    public function applyToItem(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        array $identifiers,
        ?Operation $operation = null,
        array $context = []
    ): void {
        $this->filtrerSiNecessaire($queryBuilder, $resourceClass);
    }

    /**
     * Ajoute le filtre WHERE isActive = true si :
     * - la ressource est un BreathingExercise
     * - l'utilisateur n'est pas admin
     */
    private function filtrerSiNecessaire(QueryBuilder $queryBuilder, string $resourceClass): void
    {
        // N'intervient que sur les BreathingExercise
        if ($resourceClass !== BreathingExercise::class) {
            return;
        }

        // Les admins voient tous les exercices (actifs ET inactifs)
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        // Pour les utilisateurs normaux : filtre sur isActive = true uniquement
        $alias = $queryBuilder->getRootAliases()[0];
        $queryBuilder
            ->andWhere("$alias.isActive = :estActif")
            ->setParameter('estActif', true);
    }
}
