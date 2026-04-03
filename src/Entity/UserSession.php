<?php

// src/Entity/UserSession.php
// Entité représentant une session d'exercice de respiration effectuée par un utilisateur.
// Enregistre quand l'exercice a commencé, quand il s'est terminé, et son statut.
// Permet de construire l'historique personnel de l'utilisateur (F22, F23).

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use App\Repository\UserSessionRepository;
use App\State\UserSessionStateProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserSessionRepository::class)]
#[ORM\Table(name: 'user_sessions')]
#[ApiResource(
    normalizationContext: ['groups' => ['session:read']],
    denormalizationContext: ['groups' => ['session:write']],
    operations: [
        // Voir son historique : utilisateur connecté uniquement
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Get(security: "is_granted('ROLE_USER') and object.getUser() == user"),
        // Démarrer une session : notre processor injecte l'utilisateur connecté automatiquement
        new Post(
            security: "is_granted('ROLE_USER')",
            processor: UserSessionStateProcessor::class
        ),
        // Mettre à jour le statut (terminer / abandonner)
        new Patch(security: "is_granted('ROLE_USER') and object.getUser() == user"),
    ]
)]
class UserSession
{
    // Statuts possibles d'une session
    const STATUS_STARTED   = 'started';
    const STATUS_COMPLETED = 'completed';
    const STATUS_ABANDONED = 'abandoned';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['session:read'])]
    private ?int $id = null;

    // Statut de la session : started / completed / abandoned
    #[ORM\Column(length: 20)]
    #[Groups(['session:read', 'session:write'])]
    #[Assert\Choice(
        choices: [self::STATUS_STARTED, self::STATUS_COMPLETED, self::STATUS_ABANDONED],
        message: 'Le statut doit être : started, completed ou abandoned.'
    )]
    private string $status = self::STATUS_STARTED;

    // Date/heure de début de la session
    #[ORM\Column]
    #[Groups(['session:read'])]
    private ?\DateTimeImmutable $startedAt = null;

    // Date/heure de fin (null si la session est encore en cours)
    #[ORM\Column(nullable: true)]
    #[Groups(['session:read', 'session:write'])]
    private ?\DateTimeImmutable $endedAt = null;

    // Utilisateur qui a effectué la session
    #[ORM\ManyToOne(inversedBy: 'sessions')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['session:read'])]
    private ?User $user = null;

    // Exercice de respiration concerné par cette session
    #[ORM\ManyToOne(inversedBy: 'sessions')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['session:read', 'session:write'])]
    private ?BreathingExercise $breathingExercise = null;

    public function __construct()
    {
        $this->startedAt = new \DateTimeImmutable();
    }

    // Calcule la durée de la session en secondes (null si pas encore terminée)
    public function getDureeSecondes(): ?int
    {
        if (!$this->endedAt || !$this->startedAt) {
            return null;
        }
        return $this->endedAt->getTimestamp() - $this->startedAt->getTimestamp();
    }

    public function getId(): ?int { return $this->id; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getStartedAt(): ?\DateTimeImmutable { return $this->startedAt; }
    public function setStartedAt(\DateTimeImmutable $startedAt): static { $this->startedAt = $startedAt; return $this; }
    public function getEndedAt(): ?\DateTimeImmutable { return $this->endedAt; }
    public function setEndedAt(?\DateTimeImmutable $endedAt): static { $this->endedAt = $endedAt; return $this; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }
    public function getBreathingExercise(): ?BreathingExercise { return $this->breathingExercise; }
    public function setBreathingExercise(?BreathingExercise $breathingExercise): static { $this->breathingExercise = $breathingExercise; return $this; }
}
