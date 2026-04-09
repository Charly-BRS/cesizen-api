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
     * Utilise le client passé en paramètre pour éviter de re-booter le kernel
     * (WebTestCase n'autorise qu'un seul createClient() par test).
     */
    private function postJson(
        \Symfony\Bundle\FrameworkBundle\KernelBrowser $client,
        string $url,
        array $donnees
    ): \Symfony\Component\HttpFoundation\Response {
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
        $client = static::createClient();
        // Utilise un timestamp pour garantir un email unique à chaque run
        $email = 'test.register.' . time() . '@example.com';

        $reponse = $this->postJson($client, '/api/auth/register', [
            'email'    => $email,
            'password' => 'MotDePasse123!',
            'prenom'   => 'Jean',
            'nom'      => 'Test',
        ]);

        $this->assertSame(201, $reponse->getStatusCode());

        // La réponse doit contenir un objet JSON avec l'email
        $donnees = json_decode($reponse->getContent(), true);
        $this->assertArrayHasKey('email', $donnees['utilisateur']);
        $this->assertSame($email, $donnees['utilisateur']['email']);
    }

    /**
     * Un email déjà utilisé doit retourner une erreur 409 Conflict
     */
    public function testRegisterEmailDejaPrisRetourneErreur(): void
    {
        $client = static::createClient();
        $email = 'doublon.' . time() . '@example.com';

        // Première inscription → doit réussir
        $this->postJson($client, '/api/auth/register', [
            'email' => $email, 'password' => 'MotDePasse123!',
            'prenom' => 'Jean', 'nom' => 'Test',
        ]);

        // Deuxième inscription avec le même email → doit échouer (409 Conflict)
        $reponse = $this->postJson($client, '/api/auth/register', [
            'email' => $email, 'password' => 'AutreMDP123!',
            'prenom' => 'Marie', 'nom' => 'Autre',
        ]);

        $this->assertGreaterThanOrEqual(400, $reponse->getStatusCode());
    }

    /**
     * Un email invalide doit retourner une erreur de validation 422
     */
    public function testRegisterEmailInvalideRetourne422(): void
    {
        $client = static::createClient();

        $reponse = $this->postJson($client, '/api/auth/register', [
            'email'    => 'pas-un-email',
            'password' => 'MotDePasse123!',
            'prenom'   => 'Jean',
            'nom'      => 'Test',
        ]);

        $this->assertSame(422, $reponse->getStatusCode());
    }

    /**
     * Des champs obligatoires manquants doivent retourner 422
     */
    public function testRegisterSansPrenomRetourne422(): void
    {
        $client = static::createClient();

        $reponse = $this->postJson($client, '/api/auth/register', [
            'email'    => 'champs.manquants.' . time() . '@example.com',
            'password' => 'MotDePasse123!',
            // prenom manquant intentionnellement
            'nom'      => 'Test',
        ]);

        $this->assertSame(422, $reponse->getStatusCode());
    }

    // ─── Tests de connexion (/api/auth/login) ─────────────────────────────────

    /**
     * Des identifiants valides doivent retourner un token JWT → 200 OK
     */
    public function testLoginAvecIdentifiantsValides(): void
    {
        $client = static::createClient();
        $email = 'login.valide.' . time() . '@example.com';
        $motDePasse = 'MotDePasse123!';

        // Créer l'utilisateur d'abord (même client = même session HTTP)
        $this->postJson($client, '/api/auth/register', [
            'email' => $email, 'password' => $motDePasse,
            'prenom' => 'Jean', 'nom' => 'Test',
        ]);

        // Se connecter avec ces identifiants
        $reponse = $this->postJson($client, '/api/auth/login', [
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
        $client = static::createClient();
        $email = 'login.mauvais.' . time() . '@example.com';

        // Créer l'utilisateur
        $this->postJson($client, '/api/auth/register', [
            'email' => $email, 'password' => 'BonMotDePasse123!',
            'prenom' => 'Jean', 'nom' => 'Test',
        ]);

        // Tenter de se connecter avec un mauvais mot de passe
        $reponse = $this->postJson($client, '/api/auth/login', [
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
        $client = static::createClient();

        $reponse = $this->postJson($client, '/api/auth/login', [
            'email'    => 'inexistant@example.com',
            'password' => 'PeuImporte123!',
        ]);

        $this->assertSame(401, $reponse->getStatusCode());
    }
}
