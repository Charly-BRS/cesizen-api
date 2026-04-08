<?php

// src/DataFixtures/AdminFixtures.php
// Fixture créant le compte administrateur par défaut.
// Ce compte a le rôle ROLE_ADMIN et permet d'accéder au back-office.
//
// Pour exécuter : docker exec cesizen_php php bin/console doctrine:fixtures:load --append --no-interaction

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AdminFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
    ) {}

    public function load(ObjectManager $manager): void
    {
        // Vérifie si le compte admin existe déjà (évite les doublons)
        $adminExistant = $manager->getRepository(User::class)
            ->findOneBy(['email' => 'admin@cesizen.fr']);

        if ($adminExistant) {
            echo "ℹ️  Le compte admin existe déjà.\n";
            return;
        }

        $admin = new User();
        $admin->setEmail('admin@cesizen.fr');
        $admin->setPrenom('Admin');
        $admin->setNom('CESIZen');
        // ROLE_ADMIN hérite de ROLE_USER (défini dans security.yaml)
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setIsActif(true);

        // Hache le mot de passe avant de le sauvegarder
        $motDePasseHache = $this->passwordHasher->hashPassword($admin, 'Admin1234!');
        $admin->setPassword($motDePasseHache);

        $manager->persist($admin);
        $manager->flush();

        echo "✅ Compte admin créé : admin@cesizen.fr / Admin1234!\n";
    }
}
