<?php

// src/Security/UserChecker.php
// Vérificateur de compte utilisateur appelé par Symfony à deux moments clés :
//   1. Lors de la connexion (json_login) → empêche l'obtention d'un token JWT
//   2. Lors de chaque requête authentifiée (firewall JWT) → l'utilisateur est rechargé
//      depuis la base de données à chaque appel, donc une désactivation est effective
//      immédiatement pour toutes les nouvelles requêtes, même avec un token encore valide.
//
// Pour être actif, ce checker doit être déclaré dans security.yaml sur les deux firewalls :
//   - firewall "login"  → bloque la connexion des comptes désactivés
//   - firewall "api"    → bloque les requêtes API des comptes désactivés

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    /**
     * Appelé AVANT la vérification du mot de passe / du token JWT.
     * Si le compte est désactivé → exception → connexion refusée / requête rejetée (401).
     */
    public function checkPreAuth(UserInterface $user): void
    {
        // N'intervient que sur les entités User du projet
        if (!$user instanceof User) {
            return;
        }

        if (!$user->getIsActif()) {
            // Le message est renvoyé tel quel dans la réponse JSON par LexikJWT
            throw new CustomUserMessageAuthenticationException(
                'Votre compte a été désactivé. Contactez un administrateur.'
            );
        }
    }

    /**
     * Appelé APRÈS la vérification des credentials.
     * Rien à faire ici pour CESIZen.
     */
    public function checkPostAuth(UserInterface $user): void
    {
        // Pas de vérification post-authentification nécessaire
    }
}
