<?php
$pageTitle       = 'Procès-verbaux';
$pageDescription = 'Génération des fiches d\'évaluation de soutenance au format PDF.';
include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/navbar.php';
include __DIR__ . '/partials/main_open.php';
?>

<div class="page-toolbar page-toolbar--pv">
    <a href="<?= BASE_PATH ?>/pv/generer-tous" class="btn btn-theme-pv">
        <i class="fa fa-file-archive-o"></i> Générer tous les PV (ZIP)
    </a>
</div>

<div class="panel panel-default dash-panel dash-panel--pv page-table-panel">
    <div class="panel-heading"><i class="fa fa-file-pdf-o"></i> Liste des soutenances</div>
    <div class="table-responsive">
    <table class="table table-striped table-bordered">
        <thead>
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Heure</th>
                <th>Salle</th>
                <th>Étudiant</th>
                <th>Filière</th>
                <th>Encadrant</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($soutenances as $s): ?>
            <tr>
                <td><span class="label label-theme-red">#<?= (int) $s['id_stnc'] ?></span></td>
                <td><?= htmlspecialchars($s['date_cren']) ?></td>
                <td><?= substr($s['heure_debut'], 0, 5) ?>–<?= substr($s['heure_fin'], 0, 5) ?></td>
                <td>Salle <?= htmlspecialchars($s['num_salle']) ?></td>
                <td><strong><?= htmlspecialchars($s['nom_etud'] . ' ' . $s['prenom_etud']) ?></strong></td>
                <td><?= htmlspecialchars($s['filiere']) ?></td>
                <td><?= htmlspecialchars(trim(($s['nom_encadrant'] ?? '') . ' ' . ($s['prenom_encadrant'] ?? ''))) ?></td>
                <td>
                    <a href="<?= BASE_PATH ?>/pv/generer?id=<?= (int) $s['id_stnc'] ?>" class="btn btn-theme-pv btn-sm">
                        <i class="fa fa-file-pdf-o"></i> Générer PV
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($soutenances)): ?>
            <tr><td colspan="8" class="text-center text-muted">Aucune soutenance planifiée — rendez-vous sur la page Planning</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
