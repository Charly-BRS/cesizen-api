<?php

// src/State/BreathingExerciseStateProcessor.php
// State Processor pour la suppression des exercices de respiration.
//
// Règle métier :
//   Un exercice NE PEUT PAS être supprimé s'il est lié à des sessions utilisateur,
//   car cela casserait l'historique des sessions (intégrité référentielle + données utiles).
//
//   - Exercice sans session → suppression réelle (DELETE SQL)
//   - Exercice avec sessions → désactivation automatique (isActive = false)
//     + retour d'une erreur 422 pour informer l'admin de ce qui s'est passé
//
// Ce processor est branché uniquement sur l'opération Delete de BreathingExercise.

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\BreathingExercise;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class BreathingExerciseStateProcessor implements ProcessorInterface
{
    public function __construct(
        // Le processor "interne" d'API Platform qui exécute la vraie suppression
        private readonly ProcessorInterface $innerProcessor,
        // L'EntityManager Doctrine pour persister la désactivation si nécessaire
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Appelé par API Platform avant l'exécution du DELETE /api/breathing_exercises/{id}
     *
     * @param mixed     $data         L'objet à supprimer (ici un BreathingExercise)
     * @param Operation $operation    L'opération API Platform (Delete)
     * @param array     $uriVariables Les variables d'URI (ex: {id})
     * @param array     $context      Le contexte de la requête
     */
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = []
    ): mixed {
        // N'intervient que sur les BreathingExercise
        if (!$data instanceof BreathingExercise) {
            return $this->innerProcessor->process($data, $operation, $uriVariables, $context);
        }

        // Compte le nombre de sessions liées à cet exercice
        $nombreSessions = $data->getSessions()->count();

        if ($nombreSessions > 0) {
            // L'exercice a des sessions → on ne peut pas supprimer (pertes de données)
            // On le désactive à la place pour le masquer des utilisateurs
            $data->setIsActive(false);
            $this->entityManager->flush();

            // On informe l'admin via une erreur 422 :
            // l'exercice est désactivé mais pas supprimé
            throw new UnprocessableEntityHttpException(sprintf(
                'Cet exercice ne peut pas être supprimé car il est lié à %d session(s) utilisateur. '
                . 'Il a été désactivé automatiquement.',
                $nombreSessions
            ));
        }

        // Aucune session liée → suppression réelle
        return $this->innerProcessor->process($data, $operation, $uriVariables, $context);
    }
}
