<?php

// src/DataFixtures/CategorieFixtures.php
// Fixtures pour les catégories d'articles.
// Crée 5 catégories de bien-être utilisées pour classer les articles.
//
// Exécution : php bin/console doctrine:fixtures:load --append --group=categories

namespace App\DataFixtures;

use App\Entity\Categorie;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class CategorieFixtures extends Fixture implements FixtureGroupInterface
{
    // Retourne les groupes auxquels appartiennent ces fixtures
    // Permet de les exécuter séparément avec --group=categories
    public static function getGroups(): array
    {
        return ['categories'];
    }

    public function load(ObjectManager $manager): void
    {
        // Données des 5 catégories à créer
        $categories = [
            [
                'nom'         => 'Gestion du stress',
                'slug'        => 'gestion-du-stress',
                'description' => 'Techniques et conseils pour mieux gérer le stress quotidien.',
            ],
            [
                'nom'         => 'Méditation & relaxation',
                'slug'        => 'meditation-relaxation',
                'description' => 'Guides de méditation et exercices de relaxation profonde.',
            ],
            [
                'nom'         => 'Activité physique',
                'slug'        => 'activite-physique',
                'description' => 'L\'importance du mouvement pour le bien-être mental et physique.',
            ],
            [
                'nom'         => 'Alimentation & bien-être',
                'slug'        => 'alimentation-bien-etre',
                'description' => 'Comment l\'alimentation influence notre humeur et notre énergie.',
            ],
            [
                'nom'         => 'Sommeil & récupération',
                'slug'        => 'sommeil-recuperation',
                'description' => 'Améliorer la qualité du sommeil pour une meilleure récupération.',
            ],
        ];

        foreach ($categories as $donnees) {
            // Vérifie que la catégorie n'existe pas déjà (idempotent)
            $existante = $manager->getRepository(Categorie::class)->findOneBy(['slug' => $donnees['slug']]);
            if ($existante) {
                continue;
            }

            $categorie = new Categorie();
            $categorie->setNom($donnees['nom']);
            $categorie->setSlug($donnees['slug']);
            $categorie->setDescription($donnees['description']);

            $manager->persist($categorie);
        }

        $manager->flush();
        echo "✅ 5 catégories créées.\n";
    }
}
