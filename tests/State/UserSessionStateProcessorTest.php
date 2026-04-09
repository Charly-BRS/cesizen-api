<?php

// tests/State/UserSessionStateProcessorTest.php
// Tests unitaires pour le State Processor des sessions d'utilisateur
// Teste l'assignation automatique de l'utilisateur connecté aux sessions

namespace App\Tests\State;

use App\Entity\User;
use App\Entity\UserSession;
use App\State\UserSessionStateProcessor;
use ApiPlatform\Metadata\Post;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class UserSessionStateProcessorTest extends TestCase
{
    private UserSessionStateProcessor $processor;
    private $innerProcessor;
    private $security;

    protected function setUp(): void
    {
        // Mock du processor interne
        $this->innerProcessor = $this->createMock(\ApiPlatform\State\ProcessorInterface::class);

        // Mock du service Security
        $this->security = $this->createMock(Security::class);

        // Crée l'instance du processor avec les mocks
        $this->processor = new UserSessionStateProcessor(
            $this->innerProcessor,
            $this->security
        );
    }

    /**
     * Test: UserSession sans utilisateur reçoit l'utilisateur connecté
     */
    public function testProcessAssignsAuthenticatedUserToSessionWithoutUser(): void
    {
        // Arrange
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setPrenom('Jean');
        $user->setNom('Dupont');

        $session = new UserSession();
        // Session sans utilisateur assigné
        $this->assertNull($session->getUser());

        $operation = new Post();

        // Configure le mock Security pour retourner l'utilisateur
        $this->security->method('getUser')->willReturn($user);

        // Configure le mock innerProcessor
        $this->innerProcessor
            ->expects($this->once())
            ->method('process')
            ->with($session, $operation, [], [])
            ->willReturn($session);

        // Act
        $result = $this->processor->process($session, $operation, [], []);

        // Assert
        $this->assertSame($session, $result);
        $this->assertSame($user, $session->getUser());
    }

    /**
     * Test: UserSession avec utilisateur existant ne le remplace pas
     */
    public function testProcessDoesNotReplaceExistingUser(): void
    {
        // Arrange
        $existingUser = new User();
        $existingUser->setEmail('existing@example.com');
        $existingUser->setPrenom('Alice');
        $existingUser->setNom('Martin');

        $newUser = new User();
        $newUser->setEmail('new@example.com');
        $newUser->setPrenom('Bob');
        $newUser->setNom('Smith');

        $session = new UserSession();
        $session->setUser($existingUser);

        $operation = new Post();

        // Configure le mock Security pour retourner un nouvel utilisateur
        $this->security->method('getUser')->willReturn($newUser);

        // Configure le mock innerProcessor
        $this->innerProcessor
            ->method('process')
            ->with($session, $operation, [], [])
            ->willReturn($session);

        // Act
        $result = $this->processor->process($session, $operation, [], []);

        // Assert
        $this->assertSame($session, $result);
        // Vérifie que l'utilisateur existant n'a pas été remplacé
        $this->assertSame($existingUser, $session->getUser());
        $this->assertNotSame($newUser, $session->getUser());
    }

    /**
     * Test: Si aucun utilisateur n'est connecté, setUser n'est pas appelé
     */
    public function testProcessDoesNotSetUserIfNotAuthenticated(): void
    {
        // Arrange
        $session = new UserSession();
        $this->assertNull($session->getUser());

        $operation = new Post();

        // Configure le mock Security pour retourner null (pas d'utilisateur connecté)
        $this->security->method('getUser')->willReturn(null);

        // Configure le mock innerProcessor
        $this->innerProcessor
            ->method('process')
            ->with($session, $operation, [], [])
            ->willReturn($session);

        // Act
        $result = $this->processor->process($session, $operation, [], []);

        // Assert
        $this->assertSame($session, $result);
        $this->assertNull($session->getUser());
    }

    /**
     * Test: Délègue la persistance au innerProcessor
     */
    public function testProcessDelegatesInnerProcessor(): void
    {
        // Arrange
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setPrenom('Jean');
        $user->setNom('Dupont');

        $session = new UserSession();
        $operation = new Post();
        $uriVariables = ['id' => 123];
        $context = ['some' => 'context'];

        $this->security->method('getUser')->willReturn($user);

        // Mock du processus interne pour vérifier qu'il est appelé avec les bons paramètres
        $this->innerProcessor
            ->expects($this->once())
            ->method('process')
            ->with($session, $operation, $uriVariables, $context)
            ->willReturn($session);

        // Act
        $result = $this->processor->process($session, $operation, $uriVariables, $context);

        // Assert
        $this->assertSame($session, $result);
    }

    /**
     * Test: Ne modifie pas les données qui ne sont pas UserSession
     */
    public function testProcessIgnoresNonUserSessionData(): void
    {
        // Arrange
        $otherData = new \stdClass();
        $operation = new Post();

        $this->security->method('getUser')->willReturn(new User());

        $this->innerProcessor
            ->method('process')
            ->with($otherData, $operation, [], [])
            ->willReturn($otherData);

        // Act
        $result = $this->processor->process($otherData, $operation, [], []);

        // Assert
        $this->assertSame($otherData, $result);
        // Vérifie que Security::getUser() n'a pas été appelé (car ce n'est pas une UserSession)
        $this->security->expects($this->never())->method('getUser');
    }


    /**
     * Scénario réaliste: Création d'une session d'exercice par un utilisateur
     */
    public function testRealisticScenarioCreateUserSessionDuringExercise(): void
    {
        // Arrange
        $user = new User();
        $user->setEmail('exerciser@example.com');
        $user->setPrenom('Marie');
        $user->setNom('Curie');

        $session = new UserSession();
        // La session est créée sans utilisateur (le frontend ne l'envoie pas)

        $operation = new Post();

        $this->security->method('getUser')->willReturn($user);

        $this->innerProcessor
            ->expects($this->once())
            ->method('process')
            ->willReturn($session);

        // Act
        $result = $this->processor->process($session, $operation, [], []);

        // Assert
        // L'utilisateur doit être assigné automatiquement
        $this->assertSame($user, $result->getUser());
    }
}
