<?php

// src/Entity/BreathingExercise.php
// Entité représentant un exercice de respiration guidée dans CESIZen.
// Chaque exercice définit les durées (en secondes) de chaque phase :
// inspiration → apnée (pause) → expiration, ainsi que le nombre de cycles.

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\BreathingExerciseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BreathingExerciseRepository::class)]
#[ORM\Table(name: 'breathing_exercises')]
#[ApiResource(
    normalizationContext: ['groups' => ['exercise:read']],
    denormalizationContext: ['groups' => ['exercise:write']],
    operations: [
        // Lire les exercices : tout le monde (même non connecté selon CDC F20)
        new GetCollection(),
        new Get(),
        // CRUD exercices : réservé aux admins (F25)
        new Post(security: "is_granted('ROLE_ADMIN')"),
        new Patch(security: "is_granted('ROLE_ADMIN')"),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
    ]
)]
class BreathingExercise
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['exercise:read', 'session:read'])]
    private ?int $id = null;

    // Nom affiché de l'exercice (ex : "Cohérence cardiaque")
    #[ORM\Column(length: 150)]
    #[Groups(['exercise:read', 'exercise:write', 'session:read'])]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(max: 150)]
    private ?string $nom = null;

    // Identifiant URL-friendly (ex : "coherence-cardiaque")
    #[ORM\Column(length: 150, unique: true)]
    #[Groups(['exercise:read', 'exercise:write'])]
    #[Assert\NotBlank(message: 'Le slug est obligatoire.')]
    #[Assert\Regex(pattern: '/^[a-z0-9-]+$/', message: 'Le slug ne peut contenir que des minuscules, chiffres et tirets.')]
    private ?string $slug = null;

    // Description de l'exercice et de ses bienfaits
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['exercise:read', 'exercise:write'])]
    private ?string $description = null;

    // Durée de la phase d'inspiration en secondes
    #[ORM\Column]
    #[Groups(['exercise:read', 'exercise:write'])]
    #[Assert\Positive(message: 'La durée d\'inspiration doit être positive.')]
    private int $inspirationDuration = 4;

    // Durée de l'apnée (pause après inspiration) en secondes — peut être 0
    #[ORM\Column]
    #[Groups(['exercise:read', 'exercise:write'])]
    #[Assert\PositiveOrZero(message: 'La durée d\'apnée ne peut pas être négative.')]
    private int $apneaDuration = 0;

    // Durée de la phase d'expiration en secondes
    #[ORM\Column]
    #[Groups(['exercise:read', 'exercise:write'])]
    #[Assert\Positive(message: 'La durée d\'expiration doit être positive.')]
    private int $expirationDuration = 4;

    // Nombre de cycles à effectuer (1 cycle = inspire + pause + expire)
    #[ORM\Column]
    #[Groups(['exercise:read', 'exercise:write'])]
    #[Assert\Positive(message: 'Le nombre de cycles doit être positif.')]
    private int $cycles = 5;

    // Exercice fourni par défaut par l'admin (non supprimable par les users)
    #[ORM\Column]
    #[Groups(['exercise:read', 'exercise:write'])]
    private bool $isPreset = false;

    // Exercice visible et utilisable par les utilisateurs
    #[ORM\Column]
    #[Groups(['exercise:read', 'exercise:write'])]
    private bool $isActive = true;

    // Sessions effectuées sur cet exercice
    #[ORM\OneToMany(mappedBy: 'breathingExercise', targetEntity: UserSession::class)]
    private Collection $sessions;

    public function __construct()
    {
        $this->sessions = new ArrayCollection();
    }

    // Calcule la durée totale d'un cycle complet en secondes
    public function getDureeCycleSecondes(): int
    {
        return $this->inspirationDuration + $this->apneaDuration + $this->expirationDuration;
    }

    // Calcule la durée totale de l'exercice complet en secondes
    public function getDureeTotaleSecondes(): int
    {
        return $this->getDureeCycleSecondes() * $this->cycles;
    }

    public function getId(): ?int { return $this->id; }
    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): static { $this->nom = $nom; return $this; }
    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(string $slug): static { $this->slug = $slug; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getInspirationDuration(): int { return $this->inspirationDuration; }
    public function setInspirationDuration(int $d): static { $this->inspirationDuration = $d; return $this; }
    public function getApneaDuration(): int { return $this->apneaDuration; }
    public function setApneaDuration(int $d): static { $this->apneaDuration = $d; return $this; }
    public function getExpirationDuration(): int { return $this->expirationDuration; }
    public function setExpirationDuration(int $d): static { $this->expirationDuration = $d; return $this; }
    public function getCycles(): int { return $this->cycles; }
    public function setCycles(int $cycles): static { $this->cycles = $cycles; return $this; }
    public function isPreset(): bool { return $this->isPreset; }
    public function setIsPreset(bool $isPreset): static { $this->isPreset = $isPreset; return $this; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }
    public function getSessions(): Collection { return $this->sessions; }
}
