<?php

// tests/Functional/ResetPasswordEndpointTest.php
// Tests fonctionnels pour la réinitialisation de mot de passe par token.
//
// Endpoints couverts :
//   POST /api/auth/forgot-password    → génère un token et le logue
//   POST /api/auth/reset-with-token   → valide le token et met à jour le MDP
//
// Ces tests utilisent une vraie base de données (cesizen_test).
// Prérequis :
//   php bin/console doctrine:database:create --env=test
//   php bin/console doctrine:migrations:migrate --env=test --no-interaction

namespace App\Tests\Functional;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ResetPasswordEndpointTest extends WebTestCase
{
    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Envoie une requête POST JSON vers l'API.
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

    /**
     * Crée un compte utilisateur de test et retourne son email.
     * Le timestamp garantit un email unique à chaque run de tests.
     */
    private function creerUtilisateur(
        \Symfony\Bundle\FrameworkBundle\KernelBrowser $client,
        string $suffixe = ''
    ): string {
        $email = 'reset.test.' . time() . $suffixe . '@example.com';
        $this->postJson($client, '/api/auth/register', [
            'email'    => $email,
            'password' => 'MotDePasse123!',
            'prenom'   => 'Jean',
            'nom'      => 'Test',
        ]);
        return $email;
    }

    /**
     * Injecte directement un token de réinitialisation dans la base de données
     * pour un utilisateur donné (évite de lire les logs Docker dans les tests).
     *
     * Retourne le token généré.
     */
    private function injecterToken(string $email, bool $expire = false): string
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        /** @var User $utilisateur */
        $utilisateur = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        $this->assertNotNull($utilisateur, "L'utilisateur $email doit exister en base.");

        $token = bin2hex(random_bytes(32));
        $utilisateur->setResetPasswordToken($token);

        // Si $expire = true, on met une date dans le passé pour simuler un token expiré
        $expiry = $expire
            ? new \DateTimeImmutable('-2 hours')
            : new \DateTimeImmutable('+1 hour');
        $utilisateur->setResetPasswordTokenExpiry($expiry);

        $em->flush();

        return $token;
    }

    // ─── Tests pour POST /api/auth/forgot-password ────────────────────────────

    /**
     * Un email connu → 200 OK avec message générique (sans révéler le token)
     */
    public function testMotDePasseOublieEmailConnu(): void
    {
        $client = static::createClient();
        $email  = $this->creerUtilisateur($client, 'forgot1');

        $reponse = $this->postJson($client, '/api/auth/forgot-password', [
            'email' => $email,
        ]);

        // Toujours 200 — ne pas révéler si l'email existe
        $this->assertSame(200, $reponse->getStatusCode());

        // La réponse ne doit PAS contenir le token (sécurité)
        $contenu = $reponse->getContent();
        $this->assertStringNotContainsString('token', $contenu);
    }

    /**
     * Un email inconnu → 200 OK (même réponse que pour un email connu, sécurité anti-énumération)
     */
    public function testMotDePasseOublieEmailInconnu(): void
    {
        $client = static::createClient();

        $reponse = $this->postJson($client, '/api/auth/forgot-password', [
            'email' => 'email.qui.nexiste.pas@example.com',
        ]);

        // Doit retourner 200 pour ne pas révéler si l'email existe
        $this->assertSame(200, $reponse->getStatusCode());
    }

    /**
     * Corps de requête sans champ email → 400 Bad Request
     */
    public function testMotDePasseOublieSansEmail(): void
    {
        $client = static::createClient();

        // Requête sans email
        $reponse = $this->postJson($client, '/api/auth/forgot-password', []);

        $this->assertSame(400, $reponse->getStatusCode());
    }

    // ─── Tests pour POST /api/auth/reset-with-token ───────────────────────────

    /**
     * Token valide + nouveau mot de passe → 200 OK, mot de passe mis à jour
     */
    public function testReinitialiserAvecTokenValide(): void
    {
        $client = static::createClient();
        $email  = $this->creerUtilisateur($client, 'reset1');
        $token  = $this->injecterToken($email);

        $reponse = $this->postJson($client, '/api/auth/reset-with-token', [
            'token'             => $token,
            'nouveauMotDePasse' => 'NouveauMdp456!',
        ]);

        $this->assertSame(200, $reponse->getStatusCode());

        // Vérifie que la réponse contient un message de succès
        $donnees = json_decode($reponse->getContent(), true);
        $this->assertArrayHasKey('message', $donnees);
    }

    /**
     * Token invalide (aléatoire) → 400 Bad Request
     */
    public function testReinitialiserAvecTokenInvalide(): void
    {
        $client = static::createClient();

        $reponse = $this->postJson($client, '/api/auth/reset-with-token', [
            'token'             => bin2hex(random_bytes(32)), // token aléatoire inexistant
            'nouveauMotDePasse' => 'NouveauMdp456!',
        ]);

        $this->assertSame(400, $reponse->getStatusCode());
    }

    /**
     * Token expiré (date dans le passé) → 400 Bad Request
     */
    public function testReinitialiserAvecTokenExpire(): void
    {
        $client = static::createClient();
        $email  = $this->creerUtilisateur($client, 'expire1');

        // Injecte un token avec une date d'expiration dans le passé
        $token = $this->injecterToken($email, expire: true);

        $reponse = $this->postJson($client, '/api/auth/reset-with-token', [
            'token'             => $token,
            'nouveauMotDePasse' => 'NouveauMdp456!',
        ]);

        $this->assertSame(400, $reponse->getStatusCode());
    }

    /**
     * Champs manquants dans la requête → 400 Bad Request
     */
    public function testReinitialiserSansChamps(): void
    {
        $client = static::createClient();

        // Requête totalement vide
        $reponse = $this->postJson($client, '/api/auth/reset-with-token', []);

        $this->assertSame(400, $reponse->getStatusCode());
    }

    /**
     * Nouveau mot de passe trop court (moins de 8 caractères) → 400 Bad Request
     */
    public function testReinitialiserAvecMotDePasseTropCourt(): void
    {
        $client = static::createClient();
        $email  = $this->creerUtilisateur($client, 'court1');
        $token  = $this->injecterToken($email);

        $reponse = $this->postJson($client, '/api/auth/reset-with-token', [
            'token'             => $token,
            'nouveauMotDePasse' => '1234',  // trop court (< 8 chars)
        ]);

        $this->assertSame(400, $reponse->getStatusCode());
    }

    /**
     * Le token ne peut être utilisé qu'une seule fois (usage unique).
     * Après une réinitialisation réussie, une deuxième tentative avec le même token → 400.
     */
    public function testTokenUsageUnique(): void
    {
        $client = static::createClient();
        $email  = $this->creerUtilisateur($client, 'unique1');
        $token  = $this->injecterToken($email);

        // Première utilisation du token → doit réussir
        $reponse1 = $this->postJson($client, '/api/auth/reset-with-token', [
            'token'             => $token,
            'nouveauMotDePasse' => 'PremierMdp456!',
        ]);
        $this->assertSame(200, $reponse1->getStatusCode());

        // Deuxième utilisation du même token → doit échouer (token supprimé après usage)
        $reponse2 = $this->postJson($client, '/api/auth/reset-with-token', [
            'token'             => $token,
            'nouveauMotDePasse' => 'DeuxiemeMdp789!',
        ]);
        $this->assertSame(400, $reponse2->getStatusCode());
    }
}
