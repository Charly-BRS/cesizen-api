<?php

// src/Command/SupprimerComptesExpiresCommand.php
// Commande Symfony pour supprimer définitivement les comptes désactivés depuis plus de 30 jours.
//
// Utilisation :
//   php bin/console app:supprimer-comptes-expires
//   php bin/console app:supprimer-comptes-expires --dry-run   (simulation, sans suppression)
//
// À planifier en cron (exemple : tous les jours à 2h du matin) :
//   0 2 * * * docker exec cesizen_php php bin/console app:supprimer-comptes-expires
//
// Conformité RGPD :
//   Quand un utilisateur demande la désactivation de son compte via l'API,
//   on enregistre isActif=false et dateDesactivation=maintenant.
//   Cette commande supprime les comptes dont la dateDesactivation est
//   antérieure à 30 jours, garantissant la suppression définitive des données.

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:supprimer-comptes-expires',
    description: 'Supprime définitivement les comptes désactivés depuis plus de 30 jours (RGPD).',
)]
class SupprimerComptesExpiresCommand extends Command
{
    // Délai de rétention après désactivation : 30 jours
    private const JOURS_RETENTION = 30;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Simule la suppression sans modifier la base de données.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $estSimulation = $input->getOption('dry-run');

        if ($estSimulation) {
            $io->note('Mode simulation activé — aucune donnée ne sera supprimée.');
        }

        // Calcule la date limite : tout compte désactivé AVANT cette date est à supprimer
        $dateLimite = new \DateTimeImmutable('-' . self::JOURS_RETENTION . ' days');

        // Récupère les comptes désactivés depuis plus de 30 jours
        $comptesExpires = $this->userRepository->trouverComptesASupprimer($dateLimite);

        if (empty($comptesExpires)) {
            $io->success('Aucun compte expiré à supprimer.');
            return Command::SUCCESS;
        }

        $io->table(
            ['ID', 'Email', 'Désactivé le'],
            array_map(fn($u) => [
                $u->getId(),
                $u->getEmail(),
                $u->getDateDesactivation()?->format('d/m/Y H:i'),
            ], $comptesExpires)
        );

        $nombre = count($comptesExpires);
        $io->warning(sprintf('%d compte(s) seront supprimés définitivement.', $nombre));

        if ($estSimulation) {
            $io->note('Simulation terminée. Relancez sans --dry-run pour supprimer.');
            return Command::SUCCESS;
        }

        // Suppression effective de chaque compte
        foreach ($comptesExpires as $utilisateur) {
            $this->entityManager->remove($utilisateur);
        }
        $this->entityManager->flush();

        $io->success(sprintf('%d compte(s) supprimé(s) définitivement.', $nombre));

        return Command::SUCCESS;
    }
}
