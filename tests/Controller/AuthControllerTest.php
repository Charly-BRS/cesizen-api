<?php

// tests/Controller/AuthControllerTest.php
// Tests unitaires pour la logique d'authentification (AuthController)
// Focus sur la logique métier des entités User plutôt que les endpoints HTTP
// (Les endpoints HTTP nécessitent WebTestCase avec configuration Symfony complète)

namespace App\Tests\Controller;

use App\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AuthControllerTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        // Crée un validateur réel via Symfony
        $this->validator = Validation::createValidator();
    }

    /**
     * Test: Créer un utilisateur avec données valides
     */
    public function testCreateUserWithValidData(): void
    {
        // Arrange
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setPrenom('Jean');
        $user->setNom('Dupont');
        $user->setPlainPassword('SecurePassword123!');

        // Act
        $violations = $this->validator->validate($user);

        // Assert
        $this->assertCount(0, $violations);
        $this->assertSame('user@example.com', $user->getEmail());
    }

    /**
     * Test: Email est accessible
     */
    public function testEmailIsAccessible(): void
    {
        // Arrange
        $user = new User();
        $email = 'test@example.com';

        // Act
        $user->setEmail($email);

        // Assert
        $this->assertSame($email, $user->getEmail());
    }

    /**
     * Test: Prénom est accessible
     */
    public function testPrenomIsAccessible(): void
    {
        // Arrange
        $user = new User();
        $prenom = 'Jean';

        // Act
        $user->setPrenom($prenom);

        // Assert
        $this->assertSame($prenom, $user->getPrenom());
    }

    /**
     * Test: Nom est accessible
     */
    public function testNomIsAccessible(): void
    {
        // Arrange
        $user = new User();
        $nom = 'Dupont';

        // Act
        $user->setNom($nom);

        // Assert
        $this->assertSame($nom, $user->getNom());
    }

    /**
     * Test: Plain password est effacé après authentification
     */
    public function testPlainPasswordIsErased(): void
    {
        // Arrange
        $user = new User();
        $user->setPlainPassword('MyPassword123!');
        $this->assertNotNull($user->getPlainPassword());

        // Act
        $user->eraseCredentials();

        // Assert
        $this->assertNull($user->getPlainPassword());
    }

    /**
     * Scénario réaliste: Inscription d'un nouvel utilisateur
     */
    public function testRealisticScenarioUserRegistration(): void
    {
        // Arrange
        $user = new User();

        // Act
        $user->setEmail('newuser@example.com');
        $user->setPrenom('Alice');
        $user->setNom('Martin');
        $user->setPlainPassword('SecurePassword123!');

        // Valider
        $violations = $this->validator->validate($user);

        // Effacer les données sensibles (comme le ferait le contrôleur)
        $user->eraseCredentials();

        // Assert
        $this->assertCount(0, $violations);
        $this->assertSame('newuser@example.com', $user->getEmail());
        $this->assertSame('Alice', $user->getPrenom());
        $this->assertNull($user->getPlainPassword());
    }

    /**
     * Test: Utilisateur actif par défaut
     */
    public function testUserIsActivByDefault(): void
    {
        // Arrange & Act
        $user = new User();

        // Assert
        $this->assertTrue($user->getIsActif());
    }

    /**
     * Test: Utilisateur peut être désactivé
     */
    public function testUserCanBeDeactivated(): void
    {
        // Arrange
        $user = new User();
        $user->setIsActif(false);

        // Assert
        $this->assertFalse($user->getIsActif());
    }

    /**
     * Test: getUserIdentifier retourne l'email
     */
    public function testGetUserIdentifierReturnsEmail(): void
    {
        // Arrange
        $user = new User();
        $email = 'identifier@example.com';
        $user->setEmail($email);

        // Act
        $identifier = $user->getUserIdentifier();

        // Assert
        $this->assertSame($email, $identifier);
    }

    /**
     * Test: Tous les utilisateurs ont ROLE_USER
     */
    public function testAllUsersHaveRoleUser(): void
    {
        // Arrange
        $user = new User();
        $user->setRoles([]);

        // Act
        $roles = $user->getRoles();

        // Assert
        $this->assertContains('ROLE_USER', $roles);
    }

    /**
     * Test: createdAt est défini à la création
     */
    public function testCreatedAtIsSetOnInstantiation(): void
    {
        // Arrange & Act
        $user = new User();

        // Assert
        $this->assertNotNull($user->getCreatedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $user->getCreatedAt());
    }
}
