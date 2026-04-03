<?php

// src/Doctrine/ArticlePublieExtension.php
// Extension Doctrine pour filtrer automatiquement les articles selon leur statut.
//
// Problème résolu :
//   L'API retournait TOUS les articles (publiés + brouillons) à tous les
//   utilisateurs connectés. Un utilisateur normal pouvait donc lire les
//   brouillons via /api/articles, ce qui est un problème de sécurité.
//
// Solution :
//   Cette extension s'accroche au QueryBuilder d'API Platform et ajoute
//   automatiquement un filtre WHERE isPublie = true pour les collections
//   et les items, SAUF pour les administrateurs qui voient tout.

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Article;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

class ArticlePublieExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(
        // Le service Security permet de vérifier le rôle de l'utilisateur connecté
        private readonly Security $security,
    ) {
    }

    /**
     * Appliqué sur les requêtes de collection : GET /api/articles
     * Les non-admins ne voient que les articles publiés.
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
     * Appliqué sur les requêtes d'item : GET /api/articles/{id}
     * Un non-admin ne peut pas accéder à un article brouillon par son id.
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
     * Ajoute le filtre WHERE isPublie = true si :
     * - la ressource est un Article
     * - l'utilisateur n'est pas admin
     */
    private function filtrerSiNecessaire(QueryBuilder $queryBuilder, string $resourceClass): void
    {
        // N'intervient que sur les Articles
        if ($resourceClass !== Article::class) {
            return;
        }

        // Les admins voient tous les articles (publiés ET brouillons)
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        // Pour les utilisateurs normaux : filtre sur isPublie = true uniquement
        $alias = $queryBuilder->getRootAliases()[0];
        $queryBuilder
            ->andWhere("$alias.isPublie = :estPublie")
            ->setParameter('estPublie', true);
    }
}
