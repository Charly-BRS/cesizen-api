<?php

// src/Repository/ArticleRepository.php
// Dépôt Doctrine pour l'entité Article.
// Le filtrage (articles publiés, par catégorie...) est géré automatiquement
// par ArticlePublieExtension — aucune méthode manuelle n'est nécessaire.

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
}
