<?php

// tests/bootstrap.php
// Point d'entrée de PHPUnit pour les tests Symfony.
//
// On force APP_ENV=test AVANT que bootEnv() charge le fichier .env,
// sinon Dotenv lit APP_ENV=dev dans .env et Symfony démarre en mode dev.
// Sans APP_ENV=test, WebTestCase ne peut pas créer le client HTTP (framework.test=true requis).

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

// ⚠️  Ces 3 lignes doivent être AVANT bootEnv() pour que APP_ENV=test soit préservé
//     quand Dotenv charge .env (qui contient APP_ENV=dev)
$_SERVER['APP_ENV'] = 'test';
$_ENV['APP_ENV']    = 'test';
putenv('APP_ENV=test');

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG'] ?? false) {
    umask(0000);
}
