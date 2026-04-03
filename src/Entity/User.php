<?php

// src/Entity/User.php
// Entité représentant un utilisateur de l'application CESIZen.
// Implémente UserInterface pour l'authentification Symfony
// et PasswordAuthenticatedUserInterface pour le hachage du mot de passe.

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'utilisateurs')]
#[UniqueEntity(fields: ['email'], message: 'Cet email est déjà utilisé.')]
#[ApiResource(
    // Groupe de lecture : champs exposés dans les réponses API
    normalizationContext: ['groups' => ['user:read']],
    // Groupe d'écriture : champs acceptés dans les requêtes API
    denormalizationContext: ['groups' => ['user:write']],
    operations: [
        // Lister tous les utilisateurs : réservé aux admins
        new GetCollection(security: "is_granted('ROLE_ADMIN')"),
        // Voir un utilisateur : admin OU l'utilisateur lui-même
        new Get(security: "is_granted('ROLE_ADMIN') or object == user"),
        // Créer un compte : accessible à tous (inscription publique)
        new Post(security: "is_granted('PUBLIC_ACCESS')"),
        // Modifier son profil : admin OU l'utilisateur lui-même
        new Patch(security: "is_granted('ROLE_ADMIN') or object == user"),
        // Supprimer : réservé aux admins
        new Delete(security: "is_granted('ROLE_ADMIN')"),
    ]
)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    // Identifiant unique auto-généré par PostgreSQL
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user:read'])]
    private ?int $id = null;

    // Email de l'utilisateur, utilisé comme identifiant de connexion
    #[ORM\Column(length: 180, unique: true)]
    #[Groups(['user:read', 'user:write'])]
    #[Assert\NotBlank(message: 'L\'email est obligatoire.')]
    #[Assert\Email(message: 'L\'email {{ value }} n\'est pas valide.')]
    private ?string $email = null;

    // Rôles de l'utilisateur (tableau JSON en base de données)
    // Valeur par défaut : ['ROLE_USER']
    #[ORM\Column]
    #[Groups(['user:read'])]
    private array $roles = [];

    // Mot de passe haché (jamais exposé dans l'API)
    #[ORM\Column]
    private ?string $password = null;

    // Mot de passe en clair (non persisté, utilisé uniquement lors de l'inscription)
    #[Groups(['user:write'])]
    #[Assert\NotBlank(message: 'Le mot de passe est obligatoire.', groups: ['user:create'])]
    #[Assert\Length(min: 8, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.')]
    private ?string $plainPassword = null;

    // Prénom de l'utilisateur
    #[ORM\Column(length: 100)]
    #[Groups(['user:read', 'user:write'])]
    #[Assert\NotBlank(message: 'Le prénom est obligatoire.')]
    #[Assert\Length(max: 100)]
    private ?string $prenom = null;

    // Nom de famille de l'utilisateur
    #[ORM\Column(length: 100)]
    #[Groups(['user:read', 'user:write'])]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(max: 100)]
    private ?string $nom = null;

    // Indique si le compte est actif (désactivé = ne peut plus se connecter)
    #[ORM\Column]
    #[Groups(['user:read'])]
    private bool $isActif = true;

    // Date de création du compte (définie automatiquement)
    #[ORM\Column]
    #[Groups(['user:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    // Articles rédigés par cet utilisateur
    #[ORM\OneToMany(mappedBy: 'auteur', targetEntity: Article::class)]
    private Collection $articles;

    // Sessions d'exercices de respiration effectuées par cet utilisateur
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: UserSession::class)]
    private Collection $sessions;

    public function __construct()
    {
        $this->articles = new ArrayCollection();
        $this->sessions = new ArrayCollection();
        // La date de création est définie automatiquement à l'instanciation
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    // Méthode requise par UserInterface : identifiant utilisé par Symfony Security
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        // Garantit que tout utilisateur a au minimum ROLE_USER
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): static
    {
        $this->plainPassword = $plainPassword;
        return $this;
    }

    // Méthode requise par UserInterface : efface les données sensibles temporaires
    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;
        return $this;
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

    public function isActif(): bool
    {
        return $this->isActif;
    }

    public function setIsActif(bool $isActif): static
    {
        $this->isActif = $isActif;
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

    public function getArticles(): Collection
    {
        return $this->articles;
    }

    public function getSessions(): Collection
    {
        return $this->sessions;
    }
}
