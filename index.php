<?php
declare(strict_types=1);

session_start();

// ── Autoload Composer (PhpSpreadsheet, Dompdf, etc.)
$autoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    die('Dépendances manquantes. Lancez : <code>composer install</code> dans le dossier du projet.');
}
require_once $autoload;

// ── Config (contraintes métier uniquement, plus de DB)
require_once __DIR__ . '/config/constraints.php';

// ── Routeur (charge aussi toutes les classes)
require_once __DIR__ . '/config/routes.php';
