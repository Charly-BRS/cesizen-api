<?php

// src/State/ArticleStateProcessor.php
// State Processor pour les articles (Article).
//
// Problème résolu :
//   Lors d'un POST /api/articles, le champ "auteur" est nullable: false en base
//   mais ne doit PAS être envoyé par le frontend (risque de fraude : un admin
//   pourrait s'attribuer des articles d'un autre utilisateur).
//   Sans ce processor, Doctrine tente d'insérer auteur = NULL → erreur 500.
//
// Solution :
//   Ce processor intercepte la création, récupère l'utilisateur connecté
//   depuis le JWT et l'associe automatiquement comme auteur de l'article.

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Article;
use Symfony\Bundle\SecurityBundle\Security;

class ArticleStateProcessor implements ProcessorInterface
{
    public function __construct(
        // Le processor interne d'API Platform qui persiste en base via Doctrine
        private readonly ProcessorInterface $innerProcessor,
        // Le service Security de Symfony pour récupérer l'utilisateur connecté
        private readonly Security $security,
    ) {
    }

    /**
     * Appelé par API Platform avant chaque POST sur /api/articles.
     */
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = []
    ): mixed {
        // On intervient uniquement sur les objets Article
        if ($data instanceof Article) {
            // Si l'article n'a pas encore d'auteur (cas d'un POST de création)
            if ($data->getAuteur() === null) {
                // Récupère l'utilisateur authentifié depuis le token JWT
                $utilisateurConnecte = $this->security->getUser();

                if ($utilisateurConnecte !== null) {
                    $data->setAuteur($utilisateurConnecte);
                }
            }
        }

        // Délègue la persistance réelle au processor interne d'API Platform
        return $this->innerProcessor->process($data, $operation, $uriVariables, $context);
    }
}
