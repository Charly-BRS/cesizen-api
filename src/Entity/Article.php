<?php

// src/Entity/Article.php
// Entité représentant un article de bien-être dans CESIZen.
// Un article appartient à une catégorie et est rédigé par un utilisateur (auteur).

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\ArticleRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ArticleRepository::class)]
#[ORM\Table(name: 'articles')]
#[ApiResource(
    normalizationContext: ['groups' => ['article:read']],
    denormalizationContext: ['groups' => ['article:write']],
    operations: [
        // Lire les articles publiés : accessible à tous les utilisateurs connectés
        new GetCollection(),
        new Get(),
        // Créer un article : réservé aux admins
        new Post(security: "is_granted('ROLE_ADMIN')"),
        // Modifier un article : admin OU auteur de l'article
        new Patch(security: "is_granted('ROLE_ADMIN') or object.getAuteur() == user"),
        // Supprimer : réservé aux admins
        new Delete(security: "is_granted('ROLE_ADMIN')"),
    ]
)]
class Article
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['article:read'])]
    private ?int $id = null;

    // Titre de l'article
    #[ORM\Column(length: 255)]
    #[Groups(['article:read', 'article:write'])]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(max: 255)]
    private ?string $titre = null;

    // Contenu complet de l'article (texte long)
    #[ORM\Column(type: 'text')]
    #[Groups(['article:read', 'article:write'])]
    #[Assert\NotBlank(message: 'Le contenu est obligatoire.')]
    private ?string $contenu = null;

    // Auteur de l'article (relation vers User)
    // ManyToOne : plusieurs articles peuvent avoir le même auteur
    #[ORM\ManyToOne(inversedBy: 'articles')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['article:read', 'article:write'])]
    private ?User $auteur = null;

    // Catégorie de l'article (relation vers Categorie)
    #[ORM\ManyToOne(inversedBy: 'articles')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['article:read', 'article:write'])]
    private ?Categorie $categorie = null;

    // Indique si l'article est visible par les utilisateurs
    #[ORM\Column]
    #[Groups(['article:read', 'article:write'])]
    private bool $isPublie = false;

    // Date de création de l'article
    #[ORM\Column]
    #[Groups(['article:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    // Date de la dernière modification
    #[ORM\Column(nullable: true)]
    #[Groups(['article:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;
        return $this;
    }

    public function getContenu(): ?string
    {
        return $this->contenu;
    }

    public function setContenu(string $contenu): static
    {
        $this->contenu = $contenu;
        return $this;
    }

    public function getAuteur(): ?User
    {
        return $this->auteur;
    }

    public function setAuteur(?User $auteur): static
    {
        $this->auteur = $auteur;
        return $this;
    }

    public function getCategorie(): ?Categorie
    {
        return $this->categorie;
    }

    public function setCategorie(?Categorie $categorie): static
    {
        $this->categorie = $categorie;
        return $this;
    }

    public function isPublie(): bool
    {
        return $this->isPublie;
    }

    public function setIsPublie(bool $isPublie): static
    {
        $this->isPublie = $isPublie;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
