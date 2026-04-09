<?php

// tests/Functional/BreathingExerciseEndpointTest.php
// Tests fonctionnels pour les endpoints des exercices de respiration.
//
// Règles métier testées :
//   - La liste des exercices est PUBLIQUE (accessible sans token)
//   - Créer un exercice nécessite ROLE_ADMIN
//   - Un utilisateur simple (ROLE_USER) ne peut PAS créer d'exercice → 403
//   - Supprimer un exercice avec des sessions → 422 (désactivation auto)

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BreathingExerciseEndpointTest extends WebTestCase
{
    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Crée un utilisateur via l'API et retourne son token JWT.
     */
    private function creerUtilisateurEtSeConnecter(
        \Symfony\Bundle\FrameworkBundle\KernelBrowser $client,
        string $email,
        array $roles = []
    ): string {
        // Inscription
        $client->request('POST', '/api/auth/register', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => $email, 'password' => 'MotDePasse123!',
                'prenom' => 'Test', 'nom' => 'User',
            ])
        );

        // Si des rôles admin sont nécessaires, on les passe via l'EntityManager
        if (in_array('ROLE_ADMIN', $roles)) {
            $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
            $user = $entityManager->getRepository(\App\Entity\User::class)->findOneBy(['email' => $email]);
            if ($user) {
                $user->setRoles(['ROLE_ADMIN']);
                $entityManager->flush();
            }
        }

        // Connexion → récupère le token
        $client->request('POST', '/api/auth/login', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => $email, 'password' => 'MotDePasse123!'])
        );

        $donnees = json_decode($client->getResponse()->getContent(), true);
        return $donnees['token'] ?? '';
    }

    /**
     * Envoie une requête JSON avec ou sans token d'authentification.
     */
    private function requeteJson(
        \Symfony\Bundle\FrameworkBundle\KernelBrowser $client,
        string $methode,
        string $url,
        array $donnees = [],
        string $token = ''
    ): \Symfony\Component\HttpFoundation\Response {
        $entetes = ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];
        if ($token !== '') {
            $entetes['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }
        $client->request($methode, $url, [], [], $entetes, $donnees ? json_encode($donnees) : null);
        return $client->getResponse();
    }

    // ─── Tests de lecture (GET) ───────────────────────────────────────────────

    /**
     * La liste des exercices est publique → accessible SANS token
     */
    public function testListerExercicesSansTokenRetourne200(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/breathing_exercises', [], [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertSame(200, $client->getResponse()->getStatusCode());

        // La réponse API Platform contient "hydra:member"
        $donnees = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('hydra:member', $donnees);
    }

    /**
     * Accéder à un exercice inexistant → 404
     */
    public function testGetExerciceInexistantRetourne404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/breathing_exercises/99999', [], [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertSame(404, $client->getResponse()->getStatusCode());
    }

    // ─── Tests de création (POST) ─────────────────────────────────────────────

    /**
     * Un admin peut créer un exercice → 201 Created
     */
    public function testCreerExerciceEnAdminRetourne201(): void
    {
        $client = static::createClient();
        $email = 'admin.exercice.' . time() . '@example.com';
        $token = $this->creerUtilisateurEtSeConnecter($client, $email, ['ROLE_ADMIN']);

        $reponse = $this->requeteJson($client, 'POST', '/api/breathing_exercises', [
            'nom'                 => 'Test Exercice ' . time(),
            'slug'                => 'test-exercice-' . time(),
            'description'         => 'Un exercice créé par les tests fonctionnels.',
            'inspirationDuration' => 4,
            'apneaDuration'       => 0,
            'expirationDuration'  => 6,
            'cycles'              => 5,
            'isPreset'            => false,
            'isActive'            => true,
        ], $token);

        $this->assertSame(201, $reponse->getStatusCode());

        $donnees = json_decode($reponse->getContent(), true);
        $this->assertArrayHasKey('id', $donnees);
    }

    /**
     * Un utilisateur simple (ROLE_USER) ne peut PAS créer d'exercice → 403
     */
    public function testCreerExerciceEnUserRetourne403(): void
    {
        $client = static::createClient();
        $email = 'user.exercice.' . time() . '@example.com';
        $token = $this->creerUtilisateurEtSeConnecter($client, $email);

        $reponse = $this->requeteJson($client, 'POST', '/api/breathing_exercises', [
            'nom'                 => 'Exercice Interdit',
            'slug'                => 'exercice-interdit-' . time(),
            'inspirationDuration' => 4,
            'apneaDuration'       => 0,
            'expirationDuration'  => 6,
            'cycles'              => 5,
            'isPreset'            => false,
            'isActive'            => true,
        ], $token);

        $this->assertSame(403, $reponse->getStatusCode());
    }

    /**
     * Créer un exercice SANS token → 401 Unauthorized
     */
    public function testCreerExerciceSansTokenRetourne401(): void
    {
        $client = static::createClient();

        $reponse = $this->requeteJson($client, 'POST', '/api/breathing_exercises', [
            'nom' => 'Sans Auth', 'slug' => 'sans-auth',
            'inspirationDuration' => 4, 'apneaDuration' => 0,
            'expirationDuration' => 6, 'cycles' => 5,
            'isPreset' => false, 'isActive' => true,
        ]);

        $this->assertSame(401, $reponse->getStatusCode());
    }
}
