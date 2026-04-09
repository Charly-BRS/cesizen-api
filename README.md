# CESIZen API

API REST de l'application de bien-être mental **CESIZen**, construite avec **Symfony 7.2** et **API Platform 3**.

## Stack technique

| Composant | Version |
|---|---|
| PHP | 8.4 |
| Symfony | 7.2 |
| API Platform | 3.x |
| PostgreSQL | 16 |
| LexikJWT | 3.x |
| Docker | Compose v2 |

---

## Démarrage rapide (Docker)

```bash
# 1. Cloner et aller dans le dossier
cd cesizen-api

# 2. Lancer l'infrastructure
docker compose up -d

# 3. Jouer les migrations
docker compose exec php-fpm php bin/console doctrine:migrations:migrate

# 4. (Optionnel) Charger des données de démo
docker compose exec php-fpm php bin/console doctrine:fixtures:load
```

L'API est accessible sur `http://localhost:8080/api`
La documentation Swagger est sur `http://localhost:8080/api/docs`

---

## Démarrage sans Docker

```bash
# Prérequis : PHP 8.4, Composer, PostgreSQL 16

composer install
cp .env .env.local   # puis éditer DATABASE_URL et JWT_PASSPHRASE

# Générer les clés JWT
php bin/console lexik:jwt:generate-keypair

# Créer la base et jouer les migrations
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate

# Lancer le serveur de développement
symfony serve
```

---

## Principaux endpoints

| Méthode | URL | Auth requise | Description |
|---|---|---|---|
| POST | `/api/auth/register` | Non | Créer un compte |
| POST | `/api/auth/login` | Non | Se connecter (retourne JWT) |
| POST | `/api/auth/change-password` | Oui | Changer son mot de passe |
| GET | `/api/articles` | Oui | Lister les articles publiés |
| GET | `/api/articles/{id}` | Oui | Détail d'un article |
| POST | `/api/articles` | Admin | Créer un article |
| GET | `/api/breathing_exercises` | Non | Lister les exercices actifs |
| POST | `/api/breathing_exercises` | Admin | Créer un exercice |
| DELETE | `/api/breathing_exercises/{id}` | Admin | Supprimer (ou désactiver) |
| GET | `/api/users` | Admin | Lister les utilisateurs |
| PATCH | `/api/users/{id}` | Oui | Modifier son profil |

---

## Structure du projet

```
src/
├── Controller/         # Contrôleurs custom (auth, changement MDP…)
├── Doctrine/           # Extensions de filtre (ArticlePublieExtension, ExerciceActifExtension)
├── Entity/             # Entités Doctrine (User, Article, Categorie, BreathingExercise…)
├── Repository/         # Repositories Doctrine
├── Security/           # UserChecker (blocage comptes désactivés)
├── State/              # StateProcessors API Platform (BreathingExercise, User register…)
└── Service/            # Services métier (PasswordHasher…)

tests/
├── Unit/               # Tests unitaires (Entity + StateProcessor)
└── Functional/         # Tests fonctionnels HTTP (WebTestCase)
```

---

## Lancer les tests

```bash
# Prérequis : base de données de test
php bin/console doctrine:database:create --env=test
php bin/console doctrine:migrations:migrate --env=test

# Lancer tous les tests
vendor/bin/phpunit

# Lancer uniquement les tests fonctionnels
vendor/bin/phpunit tests/Functional/
```

**28 tests** au total (18 unitaires + 10 fonctionnels) — tous verts ✅

---

## Règles métier importantes

- **Articles** : seuls les articles `isPublie=true` sont visibles par les utilisateurs normaux. Les admins voient tout.
- **Exercices** : seuls les exercices `isActive=true` sont visibles publiquement. Supprimer un exercice utilisé en session le désactive au lieu de le supprimer.
- **Comptes désactivés** : un utilisateur avec `isActif=false` ne peut pas se connecter (UserChecker).
- **JWT TTL** : 24 heures (configurable via `JWT_TTL` dans `.env`).
