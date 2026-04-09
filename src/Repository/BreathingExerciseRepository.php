<?php

// src/Repository/BreathingExerciseRepository.php
// Dépôt Doctrine pour l'entité BreathingExercise.
// Le filtrage (exercices actifs, presets...) est géré automatiquement
// par ExerciceActifExtension — aucune méthode manuelle n'est nécessaire.

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
}
