<?php

// src/Repository/CategorieRepository.php
// Dépôt Doctrine pour l'entité Categorie.
// Contient les méthodes de recherche personnalisées pour les catégories.

namespace App\Repository;

use App\Entity\Categorie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Categorie>
 */
class CategorieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Categorie::class);
    }

    // Recherche une catégorie par son slug (utilisé dans les URLs)
    public function trouverParSlug(string $slug): ?Categorie
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    // Retourne toutes les catégories triées par nom
    public function trouverToutesTriees(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
