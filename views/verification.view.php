<?php
$pageTitle       = 'Vérification';
$pageDescription = 'Contrôle automatique des contraintes du planning et de la composition des jurys.';
include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/navbar.php';
include __DIR__ . '/partials/main_open.php';

$erreurs        = $resultats['erreurs'];
$avertissements = $resultats['avertissements'];
$nbErr  = count($erreurs);
$nbWarn = count($avertissements);

// Choisir la couleur du résumé
if ($nbErr > 0) {
    $classeResume = 'page-resume--danger';
    $icone = 'fa-exclamation-triangle';
} elseif ($nbWarn > 0) {
    $classeResume = 'page-resume--warn';
    $icone = 'fa-bolt';
} else {
    $classeResume = 'page-resume--ok';
    $icone = 'fa-check-circle';
}
?>

<div class="page-resume <?= $classeResume ?>">
    <i class="fa <?= $icone ?>"></i>
    <strong>Résumé global :</strong>
    <?= $nbErr ?> erreur(s) bloquante(s) · <?= $nbWarn ?> avertissement(s)
    <?php if ($nbErr === 0 && $nbWarn === 0): ?>
        · Planning valide
    <?php endif; ?>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="panel panel-default dash-panel dash-panel--verif-errors">
            <div class="panel-heading">
                <i class="fa fa-times-circle"></i> <strong>Erreurs bloquantes</strong>
                <span class="badge pull-right"><?= $nbErr ?></span>
            </div>
            <ul class="list-group verif-list">
                <?php foreach ($erreurs as $msg): ?>
                    <li class="list-group-item list-group-item-danger">
                        <i class="fa fa-exclamation-circle"></i><?= htmlspecialchars($msg) ?>
                    </li>
                <?php endforeach; ?>
                <?php if (empty($erreurs)): ?>
                    <li class="list-group-item list-group-item-success">
                        <i class="fa fa-check"></i> Aucune erreur bloquante
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <div class="col-md-6">
        <div class="panel panel-default dash-panel dash-panel--verif-warn">
            <div class="panel-heading">
                <i class="fa fa-exclamation-triangle"></i> <strong>Avertissements</strong>
                <span class="badge pull-right"><?= $nbWarn ?></span>
            </div>
            <ul class="list-group verif-list">
                <?php foreach ($avertissements as $msg): ?>
                    <li class="list-group-item list-group-item-warning">
                        <i class="fa fa-bolt"></i><?= htmlspecialchars($msg) ?>
                    </li>
                <?php endforeach; ?>
                <?php if (empty($avertissements)): ?>
                    <li class="list-group-item list-group-item-success">
                        <i class="fa fa-check"></i> Aucun avertissement
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
