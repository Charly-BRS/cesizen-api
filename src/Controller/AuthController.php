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

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
    // Génère un token de réinitialisation valable 1 heure et le stocke sur l'utilisateur.
    // Note : en production, le token serait envoyé par email.
    //        Dans cette version démo (pas de serveur SMTP), il est retourné directement
    //        dans la réponse pour pouvoir être testé.
    //
    // Reçoit : { "email": "..." }
    // Retourne : 200 { "message": "...", "token": "ABCD1234" }  ← token pour le reset
    //            (même réponse si l'email n'existe pas, pour éviter l'énumération)
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

        // Réponse générique pour éviter l'énumération des comptes
        $reponseGenerique = [
            'message' => "Si cet email est associé à un compte, un code de réinitialisation a été généré.",
        ];

        // Cherche l'utilisateur par email
        $utilisateur = $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['email' => $email]);

        // Même si l'utilisateur n'existe pas, on retourne la même réponse (sécurité)
        if (!$utilisateur) {
            return $this->json($reponseGenerique);
        }

        // Génère un token alphanumérique de 8 caractères
        $caracteres = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $token = '';
        for ($i = 0; $i < 8; $i++) {
            $token .= $caracteres[random_int(0, strlen($caracteres) - 1)];
        }

        // Stocke le token et sa date d'expiration (1 heure)
        $utilisateur->setResetPasswordToken($token);
        $utilisateur->setResetPasswordTokenExpiry(
            new \DateTimeImmutable('+1 hour')
        );
        $this->entityManager->flush();

        // En production : envoyer le token par email
        // En démo : retourner le token directement dans la réponse
        return $this->json([
            'message' => $reponseGenerique['message'],
            'token'   => $token, // ← À remplacer par envoi email en production
            'note'    => 'Mode démo : en production, ce code serait envoyé par email.',
        ]);
    }

    // ─── Réinitialiser le MDP avec le token (public) ─────────────────────────────
    //
    // Valide le token reçu (email + token + expiry), puis met à jour le mot de passe.
    // Le token est supprimé après utilisation (usage unique).
    //
    // Reçoit : { "email": "...", "token": "ABCD1234", "nouveauMotDePasse": "..." }
    // Retourne : 200 { "message": "..." }
    #[Route('/reset-with-token', name: 'api_auth_reset_with_token', methods: ['POST'])]
    public function reinitialiserAvecToken(Request $request): JsonResponse
    {
        $donnees          = json_decode($request->getContent(), true);
        $email            = trim($donnees['email'] ?? '');
        $token            = strtoupper(trim($donnees['token'] ?? ''));
        $nouveauMotDePasse = $donnees['nouveauMotDePasse'] ?? '';

        if (!$email || !$token || !$nouveauMotDePasse) {
            return $this->json(
                ['message' => "Les champs email, token et nouveauMotDePasse sont obligatoires."],
                Response::HTTP_BAD_REQUEST
            );
        }

        if (strlen($nouveauMotDePasse) < 8) {
            return $this->json(
                ['message' => 'Le nouveau mot de passe doit contenir au moins 8 caractères.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Cherche l'utilisateur par email ET token (double vérification)
        $utilisateur = $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['email' => $email, 'resetPasswordToken' => $token]);

        if (!$utilisateur) {
            return $this->json(
                ['message' => 'Code invalide ou email incorrect.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Vérifie que le token n'est pas expiré
        $expiry = $utilisateur->getResetPasswordTokenExpiry();
        if (!$expiry || $expiry < new \DateTimeImmutable()) {
            return $this->json(
                ['message' => 'Ce code a expiré. Fais une nouvelle demande.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Réinitialise le mot de passe
        $motDePasseHache = $this->passwordHasher->hashPassword($utilisateur, $nouveauMotDePasse);
        $utilisateur->setPassword($motDePasseHache);

        // Supprime le token (usage unique)
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
