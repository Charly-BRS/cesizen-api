<?php

// tests/Functional/AuthEndpointTest.php
// Tests fonctionnels pour les endpoints d'authentification.
//
// Différence avec les tests unitaires :
//   Ces tests envoient de VRAIES requêtes HTTP via un client Symfony simulé
//   et touchent une vraie base de données (cesizen_test).
//   Ils vérifient que les endpoints se comportent correctement de bout en bout.
//
// Prérequis : base de données de test créée + migrations jouées (voir CI/CD)
// Commande locale : php bin/console doctrine:database:create --env=test
//                   php bin/console doctrine:migrations:migrate --env=test

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AuthEndpointTest extends WebTestCase
{
    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Envoie une requête POST JSON vers l'API.
     * Simplifie l'écriture des tests en regroupant la logique commune.
     */
    private function postJson(string $url, array $donnees): \Symfony\Component\HttpFoundation\Response
    {
        $client = static::createClient();
        $client->request(
            'POST',
            $url,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($donnees)
        );
        return $client->getResponse();
    }

    // ─── Tests d'inscription (/api/auth/register) ─────────────────────────────

    /**
     * Un utilisateur avec des données valides doit être créé → 201 Created
     */
    public function testRegisterAvecDonneesValidesRetourne201(): void
    {
        // Utilise un timestamp pour garantir un email unique à chaque run
        $email = 'test.register.' . time() . '@example.com';

        $reponse = $this->postJson('/api/auth/register', [
            'email'         => $email,
            'plainPassword' => 'MotDePasse123!',
            'prenom'        => 'Jean',
            'nom'           => 'Test',
        ]);

        $this->assertSame(201, $reponse->getStatusCode());

        // La réponse doit contenir un objet JSON avec l'email
        $donnees = json_decode($reponse->getContent(), true);
        $this->assertArrayHasKey('email', $donnees);
        $this->assertSame($email, $donnees['email']);
    }

    /**
     * Un email déjà utilisé doit retourner une erreur 422 (ou 400)
     */
    public function testRegisterEmailDejaPrisRetourneErreur(): void
    {
        $email = 'doublon.' . time() . '@example.com';

        // Première inscription → doit réussir
        $this->postJson('/api/auth/register', [
            'email' => $email, 'plainPassword' => 'MotDePasse123!',
            'prenom' => 'Jean', 'nom' => 'Test',
        ]);

        // Deuxième inscription avec le même email → doit échouer
        $reponse = $this->postJson('/api/auth/register', [
            'email' => $email, 'plainPassword' => 'AutreMDP123!',
            'prenom' => 'Marie', 'nom' => 'Autre',
        ]);

        // API Platform retourne 422 pour les erreurs de validation
        $this->assertGreaterThanOrEqual(400, $reponse->getStatusCode());
    }

    /**
     * Un email invalide doit retourner une erreur de validation 422
     */
    public function testRegisterEmailInvalideRetourne422(): void
    {
        $reponse = $this->postJson('/api/auth/register', [
            'email'         => 'pas-un-email',
            'plainPassword' => 'MotDePasse123!',
            'prenom'        => 'Jean',
            'nom'           => 'Test',
        ]);

        $this->assertSame(422, $reponse->getStatusCode());
    }

    /**
     * Des champs obligatoires manquants doivent retourner 422
     */
    public function testRegisterSansPrenomRetourne422(): void
    {
        $reponse = $this->postJson('/api/auth/register', [
            'email'         => 'champs.manquants@example.com',
            'plainPassword' => 'MotDePasse123!',
            // prenom manquant intentionnellement
            'nom'           => 'Test',
        ]);

        $this->assertSame(422, $reponse->getStatusCode());
    }

    // ─── Tests de connexion (/api/auth/login) ─────────────────────────────────

    /**
     * Des identifiants valides doivent retourner un token JWT → 200 OK
     */
    public function testLoginAvecIdentifiantsValides(): void
    {
        $email = 'login.valide.' . time() . '@example.com';
        $motDePasse = 'MotDePasse123!';

        // Créer l'utilisateur d'abord
        $this->postJson('/api/auth/register', [
            'email' => $email, 'plainPassword' => $motDePasse,
            'prenom' => 'Jean', 'nom' => 'Test',
        ]);

        // Se connecter avec ces identifiants
        $reponse = $this->postJson('/api/auth/login', [
            'email'    => $email,
            'password' => $motDePasse,
        ]);

        $this->assertSame(200, $reponse->getStatusCode());

        // La réponse doit contenir le token JWT
        $donnees = json_decode($reponse->getContent(), true);
        $this->assertArrayHasKey('token', $donnees);
        $this->assertNotEmpty($donnees['token']);
    }

    /**
     * Un mauvais mot de passe doit retourner 401 Unauthorized
     */
    public function testLoginMauvaisMotDePasseRetourne401(): void
    {
        $email = 'login.mauvais.' . time() . '@example.com';

        // Créer l'utilisateur
        $this->postJson('/api/auth/register', [
            'email' => $email, 'plainPassword' => 'BonMotDePasse123!',
            'prenom' => 'Jean', 'nom' => 'Test',
        ]);

        // Tenter de se connecter avec un mauvais mot de passe
        $reponse = $this->postJson('/api/auth/login', [
            'email'    => $email,
            'password' => 'MauvaisMotDePasse!',
        ]);

        $this->assertSame(401, $reponse->getStatusCode());
    }

    /**
     * Un email inexistant doit retourner 401 Unauthorized
     */
    public function testLoginEmailInexistantRetourne401(): void
    {
        $reponse = $this->postJson('/api/auth/login', [
            'email'    => 'inexistant@example.com',
            'password' => 'PeuImporte123!',
        ]);

        $this->assertSame(401, $reponse->getStatusCode());
    }
}
