<?php
declare(strict_types=1);

// ── Autoload des classes (sans Model.php ni Database)
$autoloadDirs = [
    __DIR__ . '/../app/Models',
    __DIR__ . '/../app/Services',
    __DIR__ . '/../app/Controllers',
    __DIR__ . '/../app/Utils',
];

// Charger SessionStore en premier (utilisé par tout le reste)
require_once __DIR__ . '/../app/Services/SessionStore.php';

foreach ($autoloadDirs as $dir) {
    if (!is_dir($dir)) continue;
    foreach (glob($dir . '/*.php') as $file) {
        // SessionStore déjà chargé, éviter double inclusion
        if (basename($file) === 'SessionStore.php') continue;
        require_once $file;
    }
}

// ── Table de routage
$routes = [
    ''          => ['DashboardCtrl', 'index'],
    'dashboard' => ['DashboardCtrl', 'index'],

    'import'                     => ['ImportCtrl', 'index'],
    'import/fichier'             => ['ImportCtrl', 'importFichier'],
    'import/supprimer' => ['ImportCtrl', 'supprimerDonnees'],

    'affectation'        => ['AffectationCtrl', 'index'],
    'affectation/lancer' => ['AffectationCtrl', 'lancer'],
    'affectation/export' => ['AffectationCtrl', 'export'],

    'planning'              => ['PlanningCtrl', 'index'],
    'planning/analyser'     => ['PlanningCtrl', 'analyser'],
    'planning/generer'      => ['PlanningCtrl', 'generer'],
    'planning/reinitialiser'=> ['PlanningCtrl', 'reinitialiser'],
    'planning/export'       => ['PlanningCtrl', 'export'],

    'pv'              => ['PVCtrl', 'index'],
    'pv/generer'      => ['PVCtrl', 'generer'],
    'pv/generer-tous' => ['PVCtrl', 'genererTous'],

    'verification' => ['VerifCtrl', 'index'],
];

// ── Résolution de l'URI
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
$basePath   = rtrim(dirname($scriptName), '/');
if ($basePath === '/') $basePath = '';
define('BASE_PATH', $basePath);

if ($basePath !== '' && strpos($requestUri, $basePath) === 0) {
    $rest = substr($requestUri, strlen($basePath));
    if ($rest === '' || $rest[0] === '/') $requestUri = $rest;
}
$uri = trim($requestUri, '/');

// ── Dispatch
if (isset($routes[$uri])) {
    [$ctrlClass, $method] = $routes[$uri];
    (new $ctrlClass())->$method();
} else {
    http_response_code(404);
    echo '<h1 style="font-family:sans-serif;color:#c0392b">404 — Page introuvable</h1>';
    echo '<p style="font-family:sans-serif"><a href="'.BASE_PATH.'/">Retour à l\'accueil</a></p>';
}
