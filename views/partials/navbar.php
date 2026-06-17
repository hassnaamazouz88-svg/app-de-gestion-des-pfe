<?php
// URL de la page en cours
$pageActuelle = $_SERVER['REQUEST_URI'];
$base = BASE_PATH;

// Fonction simple : vérifie si un lien du menu est actif
function menuActif($pageActuelle, $base, $lien) {
    $url = $base . $lien;
    if ($lien === '/dashboard' || $lien === '/') {
        return ($pageActuelle === $base || $pageActuelle === $base . '/' || strpos($pageActuelle, $base . '/dashboard') === 0);
    }
    return strpos($pageActuelle, $url) === 0;
}

// Liste des pages du menu
$menu = [
    ['lien' => '/dashboard',   'label' => 'Tableau de bord', 'icone' => 'fa-dashboard'],
    ['lien' => '/import',      'label' => 'Import',          'icone' => 'fa-upload'],
    ['lien' => '/affectation', 'label' => 'Affectation',     'icone' => 'fa-users'],
    ['lien' => '/planning',    'label' => 'Planning',        'icone' => 'fa-calendar'],
    ['lien' => '/pv',          'label' => 'PV',              'icone' => 'fa-file-pdf-o'],
    ['lien' => '/verification','label' => 'Vérification',    'icone' => 'fa-check-circle'],
];
?>
<nav class="navbar navbar-inverse navbar-static-top">
    <div class="container-fluid">
        <div class="navbar-header">
            <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#menu-principal">
                <span class="sr-only">Menu</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <a class="navbar-brand" href="<?= $base ?>/dashboard">
                <i class="fa fa-graduation-cap"></i> Gestion PFE
            </a>
        </div>
        <div class="collapse navbar-collapse" id="menu-principal">
            <ul class="nav navbar-nav">
                <?php foreach ($menu as $item): ?>
                    <?php $actif = menuActif($pageActuelle, $base, $item['lien']); ?>
                    <li<?= $actif ? ' class="active"' : '' ?>>
                        <a href="<?= $base . $item['lien'] ?>">
                            <i class="fa <?= $item['icone'] ?>"></i> <?= htmlspecialchars($item['label']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</nav>
