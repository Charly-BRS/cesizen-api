<?php

// src/Repository/CategorieRepository.php
// Dépôt Doctrine pour l'entité Categorie.
// API Platform retourne les catégories via les opérations standard GetCollection/Get —
// aucune méthode de recherche personnalisée n'est nécessaire.

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
}
