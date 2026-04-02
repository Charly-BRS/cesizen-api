<?php

// src/Repository/ArticleRepository.php
// Dépôt Doctrine pour l'entité Article.
// Contient les méthodes de recherche personnalisées pour les articles.

namespace App\Repository;

use App\Entity\Article;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Article>
 */
class ArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }

    // Retourne tous les articles publiés, du plus récent au plus ancien
    public function trouverTousPublies(): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.isPublie = :publie')
            ->setParameter('publie', true)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // Retourne les articles publiés d'une catégorie donnée
    public function trouverParCategorie(int $categorieId): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.isPublie = :publie')
            ->andWhere('a.categorie = :categorieId')
            ->setParameter('publie', true)
            ->setParameter('categorieId', $categorieId)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
