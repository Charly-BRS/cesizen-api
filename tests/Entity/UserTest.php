<?php

// tests/Entity/UserTest.php
// Tests unitaires pour l'entité User
// Teste les getters/setters, validations et logique métier

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        $this->user = new User();
    }

    /**
     * Test: Constructeur initialise les collections
     */
    public function testConstructorInitializesCollections(): void
    {
        // Arrange & Act
        $user = new User();

        // Assert
        $this->assertEmpty($user->getArticles());
        $this->assertEmpty($user->getSessions());
    }

    /**
     * Test: Constructeur définit createdAt
     */
    public function testConstructorSetsCreatedAt(): void
    {
        // Arrange & Act
        $beforeCreation = new \DateTimeImmutable();
        $user = new User();
        $afterCreation = new \DateTimeImmutable();

        // Assert
        $this->assertNotNull($user->getCreatedAt());
        $this->assertGreaterThanOrEqual($beforeCreation, $user->getCreatedAt());
        $this->assertLessThanOrEqual($afterCreation, $user->getCreatedAt());
    }


    /**
     * Test: getRoles ajoute toujours ROLE_USER
     */
    public function testGetRolesAlwaysIncludesRoleUser(): void
    {
        // Arrange
        $this->user->setRoles(['ROLE_ADMIN']);

        // Act
        $roles = $this->user->getRoles();

        // Assert
        $this->assertContains('ROLE_USER', $roles);
        $this->assertContains('ROLE_ADMIN', $roles);
    }

    /**
     * Test: getRoles ne duplique pas ROLE_USER
     */
    public function testGetRolesDoesNotDuplicateRoleUser(): void
    {
        // Arrange
        $this->user->setRoles(['ROLE_USER']);

        // Act
        $roles = $this->user->getRoles();

        // Assert
        $roleUserCount = count(array_filter($roles, fn($r) => $r === 'ROLE_USER'));
        $this->assertSame(1, $roleUserCount);
    }

    /**
     * Test: getUserIdentifier retourne l'email
     */
    public function testGetUserIdentifierReturnsEmail(): void
    {
        // Arrange
        $email = 'user@example.com';
        $this->user->setEmail($email);

        // Act
        $identifier = $this->user->getUserIdentifier();

        // Assert
        $this->assertSame($email, $identifier);
    }

    /**
     * Test: eraseCredentials efface plainPassword
     */
    public function testEraseCredentialsErasesPlainPassword(): void
    {
        // Arrange
        $this->user->setPlainPassword('MyPassword123!');
        $this->assertNotNull($this->user->getPlainPassword());

        // Act
        $this->user->eraseCredentials();

        // Assert
        $this->assertNull($this->user->getPlainPassword());
    }

    /**
     * Test: eraseCredentials ne touche pas au password haché
     */
    public function testEraseCredentialsDoesNotEraseHashedPassword(): void
    {
        // Arrange
        $hashedPassword = '$2y$13$hashed.password';
        $this->user->setPassword($hashedPassword);

        // Act
        $this->user->eraseCredentials();

        // Assert
        $this->assertSame($hashedPassword, $this->user->getPassword());
    }


    /**
     * Scénario réaliste: Créer et préparer un utilisateur pour l'inscription
     */
    public function testRealisticScenarioCreateUserForRegistration(): void
    {
        // Arrange & Act
        $user = new User();
        $user->setEmail('newuser@example.com');
        $user->setPrenom('Alice');
        $user->setNom('Martin');
        $user->setPlainPassword('SecurePassword123!');

        // Assert
        $this->assertSame('newuser@example.com', $user->getEmail());
        $this->assertSame('Alice', $user->getPrenom());
        $this->assertSame('Martin', $user->getNom());
        $this->assertSame('SecurePassword123!', $user->getPlainPassword());
        $this->assertTrue($user->getIsActif());
        $this->assertContains('ROLE_USER', $user->getRoles());
        $this->assertNotNull($user->getCreatedAt());
    }

    /**
     * Scénario réaliste: Utilisateur après authentification
     */
    public function testRealisticScenarioUserAfterAuthentication(): void
    {
        // Arrange
        $user = new User();
        $user->setEmail('authenticated@example.com');
        $user->setPrenom('Bob');
        $user->setNom('Smith');
        $user->setPassword('$2y$13$hashedpassword');
        $user->setPlainPassword('originalPassword');
        $user->setRoles(['ROLE_ADMIN']);

        // Act - Après l'authentification, on efface les données sensibles
        $user->eraseCredentials();

        // Assert
        $this->assertNull($user->getPlainPassword());
        $this->assertNotNull($user->getPassword()); // Le mot de passe haché reste
        $this->assertSame('authenticated@example.com', $user->getUserIdentifier());
        $this->assertContains('ROLE_ADMIN', $user->getRoles());
    }

    /**
     * Test: Utilisateur inactif
     */
    public function testInactiveUser(): void
    {
        // Arrange & Act
        $user = new User();
        $user->setEmail('inactive@example.com');
        $user->setIsActif(false);

        // Assert
        $this->assertFalse($user->getIsActif());
    }

    /**
     * Test: Chain setter (fluent interface)
     */
    public function testChainSetters(): void
    {
        // Act
        $result = $this->user
            ->setEmail('chain@example.com')
            ->setPrenom('Jean')
            ->setNom('Dupont');

        // Assert
        $this->assertSame($this->user, $result);
        $this->assertSame('chain@example.com', $this->user->getEmail());
        $this->assertSame('Jean', $this->user->getPrenom());
        $this->assertSame('Dupont', $this->user->getNom());
    }

    /**
     * Test: Rôles multiples
     */
    public function testMultipleRoles(): void
    {
        // Arrange
        $roles = ['ROLE_ADMIN', 'ROLE_MODERATOR'];

        // Act
        $this->user->setRoles($roles);

        // Assert
        $userRoles = $this->user->getRoles();
        $this->assertContains('ROLE_ADMIN', $userRoles);
        $this->assertContains('ROLE_MODERATOR', $userRoles);
        $this->assertContains('ROLE_USER', $userRoles);
    }

    /**
     * Test: Rôles vides ajoute ROLE_USER par défaut
     */
    public function testEmptyRolesDefaultsToRoleUser(): void
    {
        // Arrange
        $this->user->setRoles([]);

        // Act
        $roles = $this->user->getRoles();

        // Assert
        $this->assertContains('ROLE_USER', $roles);
        $this->assertSame(['ROLE_USER'], $roles);
    }
}
