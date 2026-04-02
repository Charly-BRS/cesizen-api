<?php

// src/Security/UserHashPasswordSubscriber.php
// Subscriber Doctrine : hache automatiquement le mot de passe d'un User
// avant de l'insérer ou de le mettre à jour en base de données.
// Cela évite de répéter la logique de hachage dans chaque contrôleur.

namespace App\Security;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
class UserHashPasswordSubscriber
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
    ) {}

    // Appelé avant l'insertion d'un nouvel enregistrement
    public function prePersist(LifecycleEventArgs $args): void
    {
        $this->hacherMotDePasse($args->getObject());
    }

    // Appelé avant la mise à jour d'un enregistrement existant
    public function preUpdate(LifecycleEventArgs $args): void
    {
        $this->hacherMotDePasse($args->getObject());
    }

    // Hache le plainPassword si présent et l'assigne au champ password
    private function hacherMotDePasse(object $entite): void
    {
        // Ne traite que les entités User ayant un mot de passe en clair
        if (!$entite instanceof User || !$entite->getPlainPassword()) {
            return;
        }

        $motDePasseHache = $this->passwordHasher->hashPassword(
            $entite,
            $entite->getPlainPassword()
        );

        $entite->setPassword($motDePasseHache);
        $entite->eraseCredentials();
    }
}
