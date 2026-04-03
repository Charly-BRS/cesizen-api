<?php

// src/Repository/BreathingExerciseRepository.php
// Dépôt Doctrine pour l'entité BreathingExercise.
// Contient les méthodes de recherche personnalisées pour les exercices de respiration.

namespace App\Repository;

use App\Entity\BreathingExercise;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BreathingExercise>
 */
class BreathingExerciseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BreathingExercise::class);
    }

    // Retourne tous les exercices actifs, triés par nombre de cycles croissant
    public function trouverTousActifs(): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.isActive = :actif')
            ->setParameter('actif', true)
            ->orderBy('e.cycles', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // Retourne les exercices fournis par défaut (presets)
    public function trouverPresets(): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.isPreset = :preset')
            ->andWhere('e.isActive = :actif')
            ->setParameter('preset', true)
            ->setParameter('actif', true)
            ->getQuery()
            ->getResult();
    }
}
