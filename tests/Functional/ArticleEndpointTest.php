<?php

// tests/Functional/ArticleEndpointTest.php
// Tests fonctionnels pour les endpoints des articles de bien-être.
//
// Règles métier testées :
//   - Lire les articles nécessite d'être connecté (IS_AUTHENTICATED_FULLY)
//   - Un utilisateur normal NE VOIT PAS les articles non publiés (isPublie=false)
//   - Un admin VOIT tous les articles (publiés ET brouillons)
//   - Créer un article nécessite ROLE_ADMIN
//   - Un ROLE_USER ne peut pas créer d'article → 403

namespace App\Tests\Functional;

use App\Entity\Article;
use App\Entity\Categorie;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ArticleEndpointTest extends WebTestCase
{
    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Crée un utilisateur et retourne son token JWT.
     * Si roles contient ROLE_ADMIN, l'utilisateur est promu via l'EntityManager.
     */
    private function creerTokenPour(
        \Symfony\Bundle\FrameworkBundle\KernelBrowser $client,
        string $email,
        array $roles = []
    ): string {
        $client->request('POST', '/api/auth/register', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => $email, 'plainPassword' => 'MotDePasse123!', 'prenom' => 'Test', 'nom' => 'User'])
        );

        if (in_array('ROLE_ADMIN', $roles)) {
            $em = static::getContainer()->get('doctrine.orm.entity_manager');
            $user = $em->getRepository(\App\Entity\User::class)->findOneBy(['email' => $email]);
            if ($user) {
                $user->setRoles(['ROLE_ADMIN']);
                $em->flush();
            }
        }

        $client->request('POST', '/api/auth/login', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => $email, 'password' => 'MotDePasse123!'])
        );

        return json_decode($client->getResponse()->getContent(), true)['token'] ?? '';
    }

    /**
     * Crée une catégorie en base via l'EntityManager (pour les tests de création d'articles).
     */
    private function creerCategorie(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client): string
    {
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        // Réutilise une catégorie existante si disponible
        $categorie = $em->getRepository(Categorie::class)->findOneBy([]);
        if ($categorie) {
            return '/api/categories/' . $categorie->getId();
        }

        // Sinon en crée une nouvelle
        $categorie = new Categorie();
        $categorie->setNom('Bien-être');
        $categorie->setSlug('bien-etre-' . time());
        $em->persist($categorie);
        $em->flush();

        return '/api/categories/' . $categorie->getId();
    }

    // ─── Tests de lecture (GET) ───────────────────────────────────────────────

    /**
     * Lire les articles sans token → 401 (les articles sont protégés)
     */
    public function testListerArticlesSansTokenRetourne401(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/articles', [], [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertSame(401, $client->getResponse()->getStatusCode());
    }

    /**
     * Un utilisateur connecté peut lire les articles publiés → 200
     */
    public function testListerArticlesConnecteRetourne200(): void
    {
        $client = static::createClient();
        $email = 'user.articles.' . time() . '@example.com';
        $token = $this->creerTokenPour($client, $email);

        $client->request('GET', '/api/articles', [], [],
            ['HTTP_ACCEPT' => 'application/ld+json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $donnees = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('hydra:member', $donnees);
    }

    // ─── Tests de visibilité : brouillons ─────────────────────────────────────

    /**
     * Un utilisateur normal NE DOIT PAS voir les articles non publiés (isPublie = false).
     * L'extension Doctrine ArticlePublieExtension filtre automatiquement.
     */
    public function testUtilisateurNeLisePasLesArticlesBrouillons(): void
    {
        $client = static::createClient();

        // Créer un admin
        $emailAdmin = 'admin.brouillon.' . time() . '@example.com';
        $tokenAdmin = $this->creerTokenPour($client, $emailAdmin, ['ROLE_ADMIN']);

        // Créer un article NON publié via l'admin
        $categorieIri = $this->creerCategorie($client);
        $client->request('POST', '/api/articles', [], [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $tokenAdmin],
            json_encode([
                'titre'     => 'Article Brouillon Test ' . time(),
                'contenu'   => 'Contenu du brouillon.',
                'isPublie'  => false,
                'categorie' => $categorieIri,
            ])
        );
        $this->assertSame(201, $client->getResponse()->getStatusCode());
        $articleBrouillon = json_decode($client->getResponse()->getContent(), true);
        $articleId = $articleBrouillon['id'];

        // L'admin PEUT voir ce brouillon
        $client->request('GET', '/api/articles/' . $articleId, [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $tokenAdmin, 'HTTP_ACCEPT' => 'application/ld+json']
        );
        $this->assertSame(200, $client->getResponse()->getStatusCode());

        // Créer un utilisateur normal
        $emailUser = 'user.brouillon.' . time() . '@example.com';
        $tokenUser = $this->creerTokenPour($client, $emailUser);

        // L'utilisateur normal NE PEUT PAS voir ce brouillon → 404 (filtré)
        $client->request('GET', '/api/articles/' . $articleId, [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $tokenUser, 'HTTP_ACCEPT' => 'application/ld+json']
        );
        $this->assertSame(404, $client->getResponse()->getStatusCode());
    }

    // ─── Tests de création (POST) ─────────────────────────────────────────────

    /**
     * Un admin peut créer un article → 201 Created
     */
    public function testCreerArticleEnAdminRetourne201(): void
    {
        $client = static::createClient();
        $email = 'admin.create.' . time() . '@example.com';
        $token = $this->creerTokenPour($client, $email, ['ROLE_ADMIN']);
        $categorieIri = $this->creerCategorie($client);

        $client->request('POST', '/api/articles', [], [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token],
            json_encode([
                'titre'    => 'Article Test Fonctionnel ' . time(),
                'contenu'  => 'Contenu de test créé par les tests fonctionnels.',
                'isPublie' => true,
                'categorie' => $categorieIri,
            ])
        );

        $this->assertSame(201, $client->getResponse()->getStatusCode());

        $donnees = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $donnees);
        $this->assertTrue($donnees['isPublie']);
    }

    /**
     * Un utilisateur simple (ROLE_USER) ne peut PAS créer d'article → 403
     */
    public function testCreerArticleEnUserRetourne403(): void
    {
        $client = static::createClient();
        $email = 'user.create.' . time() . '@example.com';
        $token = $this->creerTokenPour($client, $email);
        $categorieIri = $this->creerCategorie($client);

        $client->request('POST', '/api/articles', [], [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token],
            json_encode([
                'titre'    => 'Article Non Autorisé',
                'contenu'  => 'Un utilisateur normal ne peut pas créer d\'article.',
                'isPublie' => true,
                'categorie' => $categorieIri,
            ])
        );

        $this->assertSame(403, $client->getResponse()->getStatusCode());
    }
}
