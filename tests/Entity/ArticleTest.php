<?php

// tests/Entity/ArticleTest.php
// Tests unitaires pour l'entité Article
// Teste les articles, leur publication et leur auteur

namespace App\Tests\Entity;

use App\Entity\Article;
use App\Entity\Categorie;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class ArticleTest extends TestCase
{
    private Article $article;
    private User $author;
    private Categorie $category;

    protected function setUp(): void
    {
        $this->article = new Article();
        $this->author = new User();
        $this->author->setEmail('author@example.com');
        $this->author->setPrenom('Jean');
        $this->author->setNom('Dupont');

        $this->category = new Categorie();
        $this->category->setNom('Santé');
    }

    /**
     * Test: Constructeur initialise createdAt
     */
    public function testConstructorSetsCreatedAt(): void
    {
        // Arrange & Act
        $beforeCreation = new \DateTimeImmutable();
        $article = new Article();
        $afterCreation = new \DateTimeImmutable();

        // Assert
        $this->assertNotNull($article->getCreatedAt());
        $this->assertGreaterThanOrEqual($beforeCreation, $article->getCreatedAt());
        $this->assertLessThanOrEqual($afterCreation, $article->getCreatedAt());
    }


    /**
     * Test: Chain setters (fluent interface)
     */
    public function testChainSetters(): void
    {
        // Act
        $result = $this->article
            ->setTitre('Test Article')
            ->setContenu('Test content')
            ->setAuteur($this->author)
            ->setCategorie($this->category);

        // Assert
        $this->assertSame($this->article, $result);
        $this->assertSame('Test Article', $this->article->getTitre());
        $this->assertSame('Test content', $this->article->getContenu());
        $this->assertSame($this->author, $this->article->getAuteur());
        $this->assertSame($this->category, $this->article->getCategorie());
    }

    /**
     * Scénario réaliste: Créer un article brouillon (non publié)
     */
    public function testRealisticScenarioDraftArticle(): void
    {
        // Act
        $this->article
            ->setTitre('Article en brouillon')
            ->setContenu('Contenu en cours de rédaction...')
            ->setAuteur($this->author)
            ->setCategorie($this->category);
            // isPublie reste false par défaut

        // Assert
        $this->assertFalse($this->article->getIsPublie());
        $this->assertSame('Article en brouillon', $this->article->getTitre());
        $this->assertSame($this->author, $this->article->getAuteur());
    }

    /**
     * Scénario réaliste: Publier un article
     */
    public function testRealisticScenarioPublishArticle(): void
    {
        // Arrange
        $this->article
            ->setTitre('Les bienfaits de la méditation')
            ->setContenu('La méditation apporte de nombreux bénéfices...')
            ->setAuteur($this->author)
            ->setCategorie($this->category);

        // Act
        $this->article->setIsPublie(true);
        $now = new \DateTimeImmutable();
        $this->article->setUpdatedAt($now);

        // Assert
        $this->assertTrue($this->article->getIsPublie());
        $this->assertNotNull($this->article->getUpdatedAt());
    }

    /**
     * Scénario réaliste: Mettre à jour un article publié
     */
    public function testRealisticScenarioUpdatePublishedArticle(): void
    {
        // Arrange
        $this->article
            ->setTitre('Article original')
            ->setContenu('Contenu original')
            ->setAuteur($this->author)
            ->setIsPublie(true);

        // Act - Mise à jour
        $this->article->setTitre('Article mis à jour');
        $this->article->setContenu('Contenu mis à jour');
        $updatedAt = new \DateTimeImmutable();
        $this->article->setUpdatedAt($updatedAt);

        // Assert
        $this->assertSame('Article mis à jour', $this->article->getTitre());
        $this->assertSame('Contenu mis à jour', $this->article->getContenu());
        $this->assertTrue($this->article->getIsPublie());
        $this->assertSame($updatedAt, $this->article->getUpdatedAt());
    }

    /**
     * Test: Article sans auteur
     */
    public function testArticleWithoutAuthor(): void
    {
        // Assert
        $this->assertNull($this->article->getAuteur());
    }

    /**
     * Test: Article sans catégorie
     */
    public function testArticleWithoutCategory(): void
    {
        // Assert
        $this->assertNull($this->article->getCategorie());
    }

    /**
     * Test: Titre longue (string)
     */
    public function testArticleWithLongTitle(): void
    {
        // Arrange
        $longTitle = str_repeat('a', 500);

        // Act
        $this->article->setTitre($longTitle);

        // Assert
        $this->assertSame($longTitle, $this->article->getTitre());
    }

    /**
     * Test: Contenu long (text)
     */
    public function testArticleWithLongContent(): void
    {
        // Arrange
        $longContent = str_repeat('Lorem ipsum dolor sit amet. ', 500);

        // Act
        $this->article->setContenu($longContent);

        // Assert
        $this->assertSame($longContent, $this->article->getContenu());
    }

}
