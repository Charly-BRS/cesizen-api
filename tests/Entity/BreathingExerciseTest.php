<?php

// tests/Entity/BreathingExerciseTest.php
// Tests unitaires pour l'entité BreathingExercise
// Teste les exercices de respiration et leurs durées

namespace App\Tests\Entity;

use App\Entity\BreathingExercise;
use PHPUnit\Framework\TestCase;

class BreathingExerciseTest extends TestCase
{
    private BreathingExercise $exercise;

    protected function setUp(): void
    {
        $this->exercise = new BreathingExercise();
    }

    /**
     * Test: Valeurs par défaut de l'exercice
     */
    public function testDefaultValues(): void
    {
        // Assert
        $this->assertSame(4, $this->exercise->getInspirationDuration());
        $this->assertSame(0, $this->exercise->getApneaDuration());
        $this->assertSame(4, $this->exercise->getExpirationDuration());
        $this->assertSame(5, $this->exercise->getCycles());
        $this->assertFalse($this->exercise->getIsPreset());
        $this->assertTrue($this->exercise->getIsActive());
    }


    /**
     * Test: Durée totale d'un cycle
     */
    public function testCalculateCycleDuration(): void
    {
        // Arrange
        $this->exercise
            ->setInspirationDuration(4)
            ->setApneaDuration(4)
            ->setExpirationDuration(6);

        // Act
        $cycleDuration =
            $this->exercise->getInspirationDuration() +
            $this->exercise->getApneaDuration() +
            $this->exercise->getExpirationDuration();

        // Assert
        $this->assertSame(14, $cycleDuration);
    }

    /**
     * Test: Durée totale de l'exercice
     */
    public function testCalculateTotalExerciseDuration(): void
    {
        // Arrange
        $this->exercise
            ->setInspirationDuration(4)
            ->setApneaDuration(4)
            ->setExpirationDuration(6)
            ->setCycles(5);

        // Act
        $cycleDuration =
            $this->exercise->getInspirationDuration() +
            $this->exercise->getApneaDuration() +
            $this->exercise->getExpirationDuration();
        $totalDuration = $cycleDuration * $this->exercise->getCycles();

        // Assert
        $this->assertSame(14, $cycleDuration);
        $this->assertSame(70, $totalDuration); // 14 * 5
    }

    /**
     * Test: Chain setters (fluent interface)
     */
    public function testChainSetters(): void
    {
        // Act
        $result = $this->exercise
            ->setNom('Test Exercise')
            ->setSlug('test-exercise')
            ->setDescription('A test exercise');

        // Assert
        $this->assertSame($this->exercise, $result);
        $this->assertSame('Test Exercise', $this->exercise->getNom());
        $this->assertSame('test-exercise', $this->exercise->getSlug());
        $this->assertSame('A test exercise', $this->exercise->getDescription());
    }

    /**
     * Scénario réaliste: Créer la cohérence cardiaque (4-4-6)
     */
    public function testRealisticScenarioCoherenceCardiaque(): void
    {
        // Act
        $this->exercise
            ->setNom('Cohérence cardiaque')
            ->setSlug('coherence-cardiaque')
            ->setDescription('Exercice de respiration pour améliorer la cohérence cardiaque.')
            ->setInspirationDuration(4)
            ->setApneaDuration(4)
            ->setExpirationDuration(6)
            ->setCycles(5)
            ->setIsActive(true)
            ->setIsPreset(true);

        // Assert
        $this->assertSame('Cohérence cardiaque', $this->exercise->getNom());
        $this->assertSame('coherence-cardiaque', $this->exercise->getSlug());
        $this->assertTrue($this->exercise->getIsActive());
        $this->assertTrue($this->exercise->getIsPreset());

        // Calcul de la durée totale
        $totalDuration =
            (4 + 4 + 6) * 5; // 14 secondes par cycle * 5 cycles = 70 secondes
        $this->assertSame(70, $totalDuration);
    }

    /**
     * Scénario réaliste: Créer la respiration simple 4-0-4
     */
    public function testRealisticScenarioSimpleBreathing(): void
    {
        // Act
        $this->exercise
            ->setNom('Respiration simple')
            ->setSlug('respiration-simple')
            ->setInspirationDuration(4)
            ->setApneaDuration(0) // Pas d'apnée
            ->setExpirationDuration(4)
            ->setCycles(10)
            ->setIsActive(true);

        // Assert
        $totalDuration = (4 + 0 + 4) * 10; // 8 secondes par cycle * 10 cycles = 80 secondes
        $this->assertSame(80, $totalDuration);
    }

    /**
     * Test: Exercice inactif ne devrait pas être utilisable
     */
    public function testInactiveExerciseNotUsable(): void
    {
        // Arrange & Act
        $this->exercise->setIsActive(false);

        // Assert
        $this->assertFalse($this->exercise->getIsActive());
    }

    /**
     * Test: Exercice preset ne devrait pas être supprimable
     */
    public function testPresetExerciseShouldNotBeRemovable(): void
    {
        // Arrange & Act
        $this->exercise->setIsPreset(true);

        // Assert
        $this->assertTrue($this->exercise->getIsPreset());
    }

}
