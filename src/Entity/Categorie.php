<?php

// src/Entity/Categorie.php
// Entité représentant une catégorie d'articles dans CESIZen.
// Exemples : "Gestion du stress", "Méditation", "Nutrition".

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\CategorieRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CategorieRepository::class)]
#[ORM\Table(name: 'categories')]
#[ApiResource(
    normalizationContext: ['groups' => ['categorie:read']],
    denormalizationContext: ['groups' => ['categorie:write']],
    operations: [
        // Lire les catégories : accessible à tous les utilisateurs connectés
        new GetCollection(),
        new Get(),
        // Créer / modifier / supprimer : réservé aux admins
        new Post(security: "is_granted('ROLE_ADMIN')"),
        new Patch(security: "is_granted('ROLE_ADMIN')"),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
    ]
)]
class Categorie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['categorie:read', 'article:read'])]
    private ?int $id = null;

    // Nom affiché de la catégorie (ex : "Gestion du stress")
    #[ORM\Column(length: 100, unique: true)]
    #[Groups(['categorie:read', 'categorie:write', 'article:read'])]
    #[Assert\NotBlank(message: 'Le nom de la catégorie est obligatoire.')]
    #[Assert\Length(max: 100)]
    private ?string $nom = null;

    // Description courte de la catégorie
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['categorie:read', 'categorie:write'])]
    private ?string $description = null;

    // Slug pour les URLs (ex : "gestion-du-stress")
    #[ORM\Column(length: 100, unique: true)]
    #[Groups(['categorie:read', 'categorie:write', 'article:read'])]
    #[Assert\NotBlank(message: 'Le slug est obligatoire.')]
    #[Assert\Regex(
        pattern: '/^[a-z0-9-]+$/',
        message: 'Le slug ne peut contenir que des lettres minuscules, chiffres et tirets.'
    )]
    private ?string $slug = null;

    // Articles appartenant à cette catégorie
    #[ORM\OneToMany(mappedBy: 'categorie', targetEntity: Article::class)]
    private Collection $articles;

    public function __construct()
    {
        $this->articles = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }

    public function getArticles(): Collection
    {
        return $this->articles;
    }
}
