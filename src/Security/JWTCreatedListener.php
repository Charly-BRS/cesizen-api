<?php

// src/Security/JWTCreatedListener.php
// Listener appelé par LexikJWTBundle juste avant la création du token JWT.
// Permet d'ajouter des données supplémentaires dans le payload du token
// (id, prenom, nom) pour éviter un appel API supplémentaire côté frontend.

namespace App\Security;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_created')]
class JWTCreatedListener
{
    // Enrichit le payload JWT avec les informations de l'utilisateur
    public function __invoke(JWTCreatedEvent $event): void
    {
        $utilisateur = $event->getUser();

        // Vérifie que l'utilisateur est bien une instance de notre entité
        if (!$utilisateur instanceof User) {
            return;
        }

        // Récupère le payload actuel (contient déjà roles et username)
        $payload = $event->getData();

        // Ajoute les données supplémentaires dans le token
        $payload['id']     = $utilisateur->getId();
        $payload['email']  = $utilisateur->getEmail();
        $payload['prenom'] = $utilisateur->getPrenom();
        $payload['nom']    = $utilisateur->getNom();

        // Met à jour le payload du token
        $event->setData($payload);
    }
}
