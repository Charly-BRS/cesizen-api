<?php

// src/State/UserSessionStateProcessor.php
// State Processor pour les sessions d'exercices (UserSession).
//
// Problème résolu :
//   Lors d'un POST /api/user_sessions, le champ "user" n'est pas envoyé par
//   le frontend (il ne doit pas être modifiable par l'utilisateur lui-même).
//   Sans ce processor, Doctrine tente d'insérer une session avec user = NULL
//   et échoue (contrainte NOT NULL en base de données → erreur 500).
//
// Solution :
//   Ce processor intercepte la création AVANT la persistance en base,
//   récupère l'utilisateur connecté via le service Security de Symfony,
//   et l'associe automatiquement à la session.

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\UserSession;
use Symfony\Bundle\SecurityBundle\Security;

class UserSessionStateProcessor implements ProcessorInterface
{
    public function __construct(
        // Le processor "interne" d'API Platform qui persiste réellement en base
        private readonly ProcessorInterface $innerProcessor,
        // Le service Security de Symfony pour récupérer l'utilisateur connecté
        private readonly Security $security,
    ) {
    }

    /**
     * Appelé automatiquement par API Platform avant chaque POST / PATCH.
     *
     * @param mixed     $data          L'objet désérialisé (ici une UserSession)
     * @param Operation $operation     L'opération API Platform (Post, Patch…)
     * @param array     $uriVariables  Les variables d'URI (ex: {id})
     * @param array     $context       Le contexte de la requête
     */
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = []
    ): mixed {
        // On intervient uniquement sur les objets UserSession
        if ($data instanceof UserSession) {
            // Si la session n'a pas encore d'utilisateur associé (cas d'un POST)
            if ($data->getUser() === null) {
                // Récupère l'utilisateur authentifié depuis le token JWT
                $utilisateurConnecte = $this->security->getUser();

                // Si l'utilisateur est bien connecté, on l'associe à la session
                if ($utilisateurConnecte !== null) {
                    $data->setUser($utilisateurConnecte);
                }
            }
        }

        // Délègue la persistance réelle au processor interne d'API Platform
        return $this->innerProcessor->process($data, $operation, $uriVariables, $context);
    }
}
