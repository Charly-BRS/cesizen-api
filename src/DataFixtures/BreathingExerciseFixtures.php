<?php

// src/DataFixtures/BreathingExerciseFixtures.php
// Fixtures Doctrine : insère les exercices de respiration par défaut en base.
// Ces exercices sont marqués "isPreset = true" : ils ne peuvent pas être supprimés
// par les utilisateurs et servent de base à l'application.
//
// Pour exécuter : docker exec cesizen_php php bin/console doctrine:fixtures:load --no-interaction

namespace App\DataFixtures;

use App\Entity\BreathingExercise;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class BreathingExerciseFixtures extends Fixture
{
    // Liste des exercices de respiration par défaut
    // Chaque exercice est défini par ses durées en secondes et son nombre de cycles
    private const EXERCICES_PAR_DEFAUT = [
        [
            'nom'                  => 'Cohérence cardiaque 5-5',
            'slug'                 => 'coherence-cardiaque-5-5',
            'description'          => 'L\'exercice de cohérence cardiaque le plus courant. '
                . 'Inspirez pendant 5 secondes, expirez pendant 5 secondes. '
                . 'Pratiqué 3 fois par jour, il réduit le stress et régule le système nerveux autonome.',
            'inspirationDuration'  => 5,
            'apneaDuration'        => 0,
            'expirationDuration'   => 5,
            'cycles'               => 6,
        ],
        [
            'nom'                  => 'Relaxation 4-7-8',
            'slug'                 => 'relaxation-4-7-8',
            'description'          => 'Technique du Dr Andrew Weil, idéale pour s\'endormir et calmer l\'anxiété. '
                . 'Inspirez 4 secondes, retenez votre souffle 7 secondes, '
                . 'expirez lentement pendant 8 secondes.',
            'inspirationDuration'  => 4,
            'apneaDuration'        => 7,
            'expirationDuration'   => 8,
            'cycles'               => 4,
        ],
        [
            'nom'                  => 'Respiration carrée 4-4-4-4',
            'slug'                 => 'respiration-carree',
            'description'          => 'Aussi appelée "Box Breathing", utilisée par les forces spéciales américaines. '
                . 'Inspirez 4 secondes, retenez 4 secondes, expirez 4 secondes, retenez 4 secondes. '
                . 'Excellent pour la concentration et la gestion du stress aigu.',
            'inspirationDuration'  => 4,
            'apneaDuration'        => 4,
            'expirationDuration'   => 4,
            'cycles'               => 5,
        ],
    ];

    // Méthode principale appelée lors du chargement des fixtures
    public function load(ObjectManager $manager): void
    {
        foreach (self::EXERCICES_PAR_DEFAUT as $donnees) {
            // Vérifie si l'exercice existe déjà (évite les doublons)
            $exerciceExistant = $manager->getRepository(BreathingExercise::class)
                ->findOneBy(['slug' => $donnees['slug']]);

            if ($exerciceExistant) {
                continue;
            }

            $exercice = new BreathingExercise();
            $exercice->setNom($donnees['nom']);
            $exercice->setSlug($donnees['slug']);
            $exercice->setDescription($donnees['description']);
            $exercice->setInspirationDuration($donnees['inspirationDuration']);
            $exercice->setApneaDuration($donnees['apneaDuration']);
            $exercice->setExpirationDuration($donnees['expirationDuration']);
            $exercice->setCycles($donnees['cycles']);
            // Marqué comme preset : géré par l'admin, pas supprimable par les users
            $exercice->setIsPreset(true);
            $exercice->setIsActive(true);

            $manager->persist($exercice);
        }

        $manager->flush();

        echo "✅ 3 exercices de respiration par défaut chargés en base.\n";
    }
}
