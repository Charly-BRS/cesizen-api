<?php

// src/Controller/AuthController.php
// Contrôleur gérant l'authentification et la gestion des comptes.
//
// Endpoints disponibles :
//   POST /api/auth/register           : inscription d'un nouvel utilisateur
//   POST /api/auth/login              : géré automatiquement par LexikJWTBundle
//   POST /api/auth/change-password    : changement de mot de passe (utilisateur connecté)
//   POST /api/auth/set-role           : définir les rôles d'un utilisateur (ROLE_ADMIN)
//   POST /api/auth/reset-password     : réinitialiser le mot de passe d'un utilisateur (ROLE_ADMIN)
//   POST /api/auth/forgot-password    : demande de réinitialisation par email (public)
//   POST /api/auth/reset-with-token   : réinitialiser le MDP avec le token reçu (public)
//
// En développement, les emails sont interceptés par Mailpit : http://localhost:8025

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/auth')]
class AuthController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private ValidatorInterface $validator,
        private Security $security,
        private MailerInterface $mailer,
        // Injecté depuis la variable d'environnement FRONTEND_URL
        // Dev  → http://localhost:5173  (défini dans cesizen-api/.env)
        // Prod → http://localhost:3000  (défini dans .env.prod racine)
        #[Autowire(env: 'FRONTEND_URL')]
        private string $frontendUrl,
    ) {}

    // ─── Inscription ────────────────────────────────────────────────────────────
    // Reçoit : { "email": "...", "password": "...", "prenom": "...", "nom": "..." }
    // Retourne : 201 { "message": "...", "utilisateur": { ... } }
    #[Route('/register', name: 'api_auth_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $donnees = json_decode($request->getContent(), true);

        if (!$donnees) {
            return $this->json(
                ['message' => 'Le corps de la requête doit être un JSON valide.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $utilisateur = new User();
        $utilisateur->setEmail($donnees['email'] ?? '');
        $utilisateur->setPrenom($donnees['prenom'] ?? '');
        $utilisateur->setNom($donnees['nom'] ?? '');
        $utilisateur->setPlainPassword($donnees['password'] ?? '');

        // Validation via les contraintes déclarées dans User.php
        $erreurs = $this->validator->validate($utilisateur);

        if (count($erreurs) > 0) {
            $erreursFormatees = [];
            foreach ($erreurs as $erreur) {
                $erreursFormatees[$erreur->getPropertyPath()] = $erreur->getMessage();
            }
            return $this->json(
                ['message' => 'Données invalides.', 'erreurs' => $erreursFormatees],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        // Vérifie que l'email n'est pas déjà pris
        $utilisateurExistant = $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['email' => $utilisateur->getEmail()]);

        if ($utilisateurExistant) {
            return $this->json(
                ['message' => 'Cet email est déjà utilisé.'],
                Response::HTTP_CONFLICT
            );
        }

        // Hachage du mot de passe
        $motDePasseHache = $this->passwordHasher->hashPassword(
            $utilisateur,
            $utilisateur->getPlainPassword()
        );
        $utilisateur->setPassword($motDePasseHache);
        $utilisateur->eraseCredentials();

        $this->entityManager->persist($utilisateur);
        $this->entityManager->flush();

        return $this->json(
            [
                'message'     => 'Compte créé avec succès. Vous pouvez maintenant vous connecter.',
                'utilisateur' => [
                    'id'     => $utilisateur->getId(),
                    'email'  => $utilisateur->getEmail(),
                    'prenom' => $utilisateur->getPrenom(),
                    'nom'    => $utilisateur->getNom(),
                ],
            ],
            Response::HTTP_CREATED
        );
    }

    // ─── Changement de mot de passe (utilisateur connecté) ───────────────────────
    // Reçoit : { "ancienMotDePasse": "...", "nouveauMotDePasse": "..." }
    // Retourne : 200 { "message": "..." }
    #[Route('/change-password', name: 'api_auth_change_password', methods: ['POST'])]
    public function changerMotDePasse(Request $request): JsonResponse
    {
        /** @var User|null $utilisateur */
        $utilisateur = $this->security->getUser();

        if (!$utilisateur instanceof User) {
            return $this->json(
                ['message' => 'Vous devez être connecté pour changer votre mot de passe.'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        $donnees = json_decode($request->getContent(), true);

        if (!$donnees) {
            return $this->json(
                ['message' => 'Le corps de la requête doit être un JSON valide.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $ancienMotDePasse  = $donnees['ancienMotDePasse'] ?? '';
        $nouveauMotDePasse = $donnees['nouveauMotDePasse'] ?? '';

        if (!$this->passwordHasher->isPasswordValid($utilisateur, $ancienMotDePasse)) {
            return $this->json(
                ['message' => "L'ancien mot de passe est incorrect."],
                Response::HTTP_BAD_REQUEST
            );
        }

        if (strlen($nouveauMotDePasse) < 8) {
            return $this->json(
                ['message' => 'Le nouveau mot de passe doit contenir au moins 8 caractères.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $motDePasseHache = $this->passwordHasher->hashPassword($utilisateur, $nouveauMotDePasse);
        $utilisateur->setPassword($motDePasseHache);
        $this->entityManager->flush();

        return $this->json(['message' => 'Mot de passe modifié avec succès.']);
    }

    // ─── Définir les rôles d'un utilisateur (ROLE_ADMIN requis) ─────────────────
    //
    // Pourquoi un endpoint dédié plutôt que PATCH /api/users/{id} ?
    //   Les rôles ne sont pas dans le groupe "user:write" (lecture seule dans l'API)
    //   pour empêcher tout utilisateur de s'auto-promouvoir. Seul cet endpoint,
    //   protégé par ROLE_ADMIN, peut modifier les rôles.
    //
    // Reçoit : { "userId": 42, "roles": ["ROLE_REDACTEUR"] }
    //   roles peut être : []  → utilisateur standard
    //                     ["ROLE_REDACTEUR"]  → rédacteur (peut créer des articles)
    //                     ["ROLE_ADMIN"]      → administrateur complet
    //
    // Retourne : 200 { "message": "...", "roles": [...] }
    #[Route('/set-role', name: 'api_auth_set_role', methods: ['POST'])]
    public function definirRole(Request $request): JsonResponse
    {
        // Vérifie que l'appelant est bien un administrateur
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            return $this->json(
                ['message' => 'Accès refusé. Rôle ROLE_ADMIN requis.'],
                Response::HTTP_FORBIDDEN
            );
        }

        $donnees = json_decode($request->getContent(), true);

        if (!$donnees || !isset($donnees['userId'])) {
            return $this->json(
                ['message' => 'Les champs "userId" et "roles" sont obligatoires.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Récupère l'utilisateur cible
        $utilisateur = $this->entityManager
            ->getRepository(User::class)
            ->find((int) $donnees['userId']);

        if (!$utilisateur) {
            return $this->json(
                ['message' => 'Utilisateur introuvable.'],
                Response::HTTP_NOT_FOUND
            );
        }

        // Valide les rôles fournis — seuls ces rôles sont autorisés
        $rolesAutorises = ['ROLE_USER', 'ROLE_REDACTEUR', 'ROLE_ADMIN'];
        $nouveauxRoles  = $donnees['roles'] ?? [];

        foreach ($nouveauxRoles as $role) {
            if (!in_array($role, $rolesAutorises, true)) {
                return $this->json(
                    ['message' => "Rôle invalide : $role. Rôles autorisés : " . implode(', ', $rolesAutorises)],
                    Response::HTTP_BAD_REQUEST
                );
            }
        }

        // Applique les rôles (ROLE_USER est toujours ajouté automatiquement par getRoles())
        // On stocke uniquement les rôles supplémentaires (pas ROLE_USER qui est implicite)
        $rolesFiltres = array_values(array_filter(
            $nouveauxRoles,
            fn(string $r) => $r !== 'ROLE_USER'
        ));

        $utilisateur->setRoles($rolesFiltres);
        $this->entityManager->flush();

        return $this->json([
            'message' => 'Rôles mis à jour avec succès.',
            'roles'   => $utilisateur->getRoles(),
        ]);
    }

    // ─── Mot de passe oublié (public) ────────────────────────────────────────────
    //
    // Génère un token de réinitialisation sécurisé (64 hex chars) valable 1 heure,
    // le stocke sur l'utilisateur, et envoie un email avec le lien.
    //
    // En développement : le mail est intercepté par Mailpit → http://localhost:8025
    // En production   : configurer MAILER_DSN avec un vrai serveur SMTP
    //
    // Reçoit : { "email": "..." }
    // Retourne : 200 { "message": "..." }  ← même réponse si l'email n'existe pas (sécurité)
    #[Route('/forgot-password', name: 'api_auth_forgot_password', methods: ['POST'])]
    public function motDePasseOublie(Request $request): JsonResponse
    {
        $donnees = json_decode($request->getContent(), true);
        $email   = trim($donnees['email'] ?? '');

        if (!$email) {
            return $this->json(
                ['message' => "L'email est obligatoire."],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Réponse générique — identique que l'email existe ou non (évite l'énumération)
        $reponseGenerique = [
            'message' => "Si cet email est associé à un compte, un lien de réinitialisation a été généré.",
        ];

        $utilisateur = $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['email' => $email]);

        if (!$utilisateur) {
            return $this->json($reponseGenerique);
        }

        // Génère un token cryptographiquement sûr : 32 bytes → 64 caractères hex
        $token = bin2hex(random_bytes(32));

        // Stocke le token et son expiration (1 heure)
        $utilisateur->setResetPasswordToken($token);
        $utilisateur->setResetPasswordTokenExpiry(new \DateTimeImmutable('+1 hour'));
        $this->entityManager->flush();

        // Construit le lien de réinitialisation pointant vers le frontend
        // L'URL de base est lue depuis FRONTEND_URL (injectée dans le constructeur)
        $lien = rtrim($this->frontendUrl, '/') . '/reset-password?token=' . $token;

        // Envoie l'email de réinitialisation via Symfony Mailer.
        // En dev : intercepté par Mailpit (http://localhost:8025)
        // En prod : transmis au serveur SMTP défini dans MAILER_DSN
        $email = (new Email())
            ->from('noreply@cesizen.fr')
            ->to($utilisateur->getEmail())
            ->subject('Réinitialisation de votre mot de passe CESIZen')
            ->text(
                "Bonjour {$utilisateur->getPrenom()},\n\n"
                . "Tu as demandé à réinitialiser ton mot de passe CESIZen.\n\n"
                . "Clique sur ce lien pour choisir un nouveau mot de passe (valable 1 heure) :\n"
                . $lien . "\n\n"
                . "Si tu n'es pas à l'origine de cette demande, ignore ce message.\n\n"
                . "L'équipe CESIZen"
            )
            ->html(
                "<p>Bonjour <strong>{$utilisateur->getPrenom()}</strong>,</p>"
                . "<p>Tu as demandé à réinitialiser ton mot de passe CESIZen.</p>"
                . "<p><a href=\"{$lien}\" style=\"background:#15803d;color:white;padding:10px 20px;"
                . "border-radius:8px;text-decoration:none;font-weight:bold;display:inline-block\">"
                . "Réinitialiser mon mot de passe</a></p>"
                . "<p>Ce lien est valable <strong>1 heure</strong>.</p>"
                . "<p>Si tu n'es pas à l'origine de cette demande, ignore ce message.</p>"
                . "<p>L'équipe CESIZen</p>"
            );

        $this->mailer->send($email);

        return $this->json($reponseGenerique);
    }

    // ─── Réinitialiser le MDP avec le token (public) ─────────────────────────────
    //
    // Valide le token (64 hex chars) et met à jour le mot de passe.
    // Le token est supprimé après utilisation (usage unique).
    // Le token identifie de façon unique l'utilisateur — pas besoin de l'email.
    //
    // Reçoit : { "token": "a3f2...", "nouveauMotDePasse": "..." }
    // Retourne : 200 { "message": "..." }
    //            400 si token invalide / expiré / champs manquants
    #[Route('/reset-with-token', name: 'api_auth_reset_with_token', methods: ['POST'])]
    public function reinitialiserAvecToken(Request $request): JsonResponse
    {
        $donnees           = json_decode($request->getContent(), true);
        $token             = trim($donnees['token'] ?? '');
        $nouveauMotDePasse = $donnees['nouveauMotDePasse'] ?? '';

        if (!$token || !$nouveauMotDePasse) {
            return $this->json(
                ['message' => 'Les champs token et nouveauMotDePasse sont obligatoires.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        if (strlen($nouveauMotDePasse) < 8) {
            return $this->json(
                ['message' => 'Le nouveau mot de passe doit contenir au moins 8 caractères.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Cherche l'utilisateur par le token (64 hex chars = suffisamment unique)
        $utilisateur = $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['resetPasswordToken' => $token]);

        if (!$utilisateur) {
            return $this->json(
                ['message' => 'Lien de réinitialisation invalide.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Vérifie que le token n'est pas expiré (durée de vie : 1 heure)
        $expiry = $utilisateur->getResetPasswordTokenExpiry();
        if (!$expiry || $expiry < new \DateTimeImmutable()) {
            return $this->json(
                ['message' => 'Ce lien a expiré. Fais une nouvelle demande.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Met à jour le mot de passe
        $motDePasseHache = $this->passwordHasher->hashPassword($utilisateur, $nouveauMotDePasse);
        $utilisateur->setPassword($motDePasseHache);

        // Invalide le token (usage unique)
        $utilisateur->setResetPasswordToken(null);
        $utilisateur->setResetPasswordTokenExpiry(null);

        $this->entityManager->flush();

        return $this->json(['message' => 'Mot de passe réinitialisé avec succès. Tu peux maintenant te connecter.']);
    }

    // ─── Réinitialiser le mot de passe d'un utilisateur (ROLE_ADMIN requis) ─────
    //
    // Permet à un administrateur de définir un nouveau mot de passe pour
    // n'importe quel utilisateur sans connaître l'ancien.
    // Cas d'usage : un utilisateur a perdu accès à son compte.
    //
    // Reçoit : { "userId": 42, "nouveauMotDePasse": "..." }
    // Retourne : 200 { "message": "..." }
    #[Route('/reset-password', name: 'api_auth_reset_password', methods: ['POST'])]
    public function reinitialiserMotDePasse(Request $request): JsonResponse
    {
        // Vérifie que l'appelant est bien un administrateur
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            return $this->json(
                ['message' => 'Accès refusé. Rôle ROLE_ADMIN requis.'],
                Response::HTTP_FORBIDDEN
            );
        }

        $donnees = json_decode($request->getContent(), true);

        if (!$donnees || !isset($donnees['userId'], $donnees['nouveauMotDePasse'])) {
            return $this->json(
                ['message' => 'Les champs "userId" et "nouveauMotDePasse" sont obligatoires.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Récupère l'utilisateur cible
        $utilisateur = $this->entityManager
            ->getRepository(User::class)
            ->find((int) $donnees['userId']);

        if (!$utilisateur) {
            return $this->json(
                ['message' => 'Utilisateur introuvable.'],
                Response::HTTP_NOT_FOUND
            );
        }

        $nouveauMotDePasse = $donnees['nouveauMotDePasse'];

        if (strlen($nouveauMotDePasse) < 8) {
            return $this->json(
                ['message' => 'Le nouveau mot de passe doit contenir au moins 8 caractères.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Hache et enregistre le nouveau mot de passe
        $motDePasseHache = $this->passwordHasher->hashPassword($utilisateur, $nouveauMotDePasse);
        $utilisateur->setPassword($motDePasseHache);
        $this->entityManager->flush();

        return $this->json([
            'message' => "Mot de passe de {$utilisateur->getPrenom()} {$utilisateur->getNom()} réinitialisé avec succès.",
        ]);
    }

    // ─── Désactivation du compte (RGPD) ────────────────────────────────────────
    // Reçoit : rien (l'utilisateur connecté est identifié par son token JWT)
    // Retourne : 200 { "message": "...", "supprimeLe": "..." }
    //
    // Met isActif = false et enregistre la date de désactivation.
    // L'utilisateur ne peut plus se connecter immédiatement (UserChecker).
    // Après 30 jours, la commande app:supprimer-comptes-expires supprime le compte.
    #[Route('/desactiver-compte', name: 'api_auth_desactiver_compte', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function desactiverCompte(): JsonResponse
    {
        /** @var User $utilisateur */
        $utilisateur = $this->security->getUser();

        if (!$utilisateur instanceof User) {
            return $this->json(
                ['message' => 'Utilisateur non authentifié.'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        // Enregistre la désactivation avec la date du jour
        $maintenant = new \DateTimeImmutable();
        $utilisateur->setIsActif(false);
        $utilisateur->setDateDesactivation($maintenant);
        $this->entityManager->flush();

        // Calcule la date de suppression définitive (J+30)
        $dateSuppression = $maintenant->modify('+30 days')->format('d/m/Y');

        return $this->json([
            'message'    => 'Votre compte a été désactivé. Toutes vos données seront supprimées définitivement le ' . $dateSuppression . '.',
            'supprimeLe' => $dateSuppression,
        ]);
    }
}
