<?php

// src/Controller/AuthController.php
// Contrôleur gérant l'authentification des utilisateurs.
// - POST /api/auth/register : inscription d'un nouvel utilisateur
// - POST /api/auth/login    : géré automatiquement par LexikJWTBundle (voir security.yaml)

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/auth')]
class AuthController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private ValidatorInterface $validator,
    ) {}

    // Inscription d'un nouvel utilisateur
    // Reçoit : { "email": "...", "password": "...", "prenom": "...", "nom": "..." }
    // Retourne : { "message": "...", "utilisateur": { ... } }
    #[Route('/register', name: 'api_auth_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        // Décode le corps de la requête JSON
        $donnees = json_decode($request->getContent(), true);

        // Vérifie que le JSON est valide
        if (!$donnees) {
            return $this->json(
                ['message' => 'Le corps de la requête doit être un JSON valide.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Crée un nouvel utilisateur avec les données reçues
        $utilisateur = new User();
        $utilisateur->setEmail($donnees['email'] ?? '');
        $utilisateur->setPrenom($donnees['prenom'] ?? '');
        $utilisateur->setNom($donnees['nom'] ?? '');
        $utilisateur->setPlainPassword($donnees['password'] ?? '');

        // Valide l'entité avec les contraintes définies dans User.php
        $erreurs = $this->validator->validate($utilisateur);

        if (count($erreurs) > 0) {
            // Transforme les erreurs de validation en tableau lisible
            $erreursFormatees = [];
            foreach ($erreurs as $erreur) {
                $erreursFormatees[$erreur->getPropertyPath()] = $erreur->getMessage();
            }

            return $this->json(
                ['message' => 'Données invalides.', 'erreurs' => $erreursFormatees],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        // Vérifie que l'email n'est pas déjà utilisé
        $utilisateurExistant = $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['email' => $utilisateur->getEmail()]);

        if ($utilisateurExistant) {
            return $this->json(
                ['message' => 'Cet email est déjà utilisé.'],
                Response::HTTP_CONFLICT
            );
        }

        // Hache le mot de passe avant de le sauvegarder
        $motDePasseHache = $this->passwordHasher->hashPassword(
            $utilisateur,
            $utilisateur->getPlainPassword()
        );
        $utilisateur->setPassword($motDePasseHache);
        // Efface le mot de passe en clair de la mémoire
        $utilisateur->eraseCredentials();

        // Persiste l'utilisateur en base de données
        $this->entityManager->persist($utilisateur);
        $this->entityManager->flush();

        return $this->json(
            [
                'message' => 'Compte créé avec succès. Vous pouvez maintenant vous connecter.',
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
}
