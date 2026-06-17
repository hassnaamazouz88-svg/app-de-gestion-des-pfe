<?php
$pageTitle       = 'Affectation';
$pageDescription = 'Attribution automatique des encadrants aux étudiants selon la spécialité et la charge de chaque professeur.';
include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/navbar.php';
include __DIR__ . '/partials/main_open.php';
?>

<div class="page-toolbar page-toolbar--affectation">
    <form action="<?= BASE_PATH ?>/affectation/lancer" method="post" style="display:inline"
          onsubmit="return confirm('Lancer l\'affectation automatique ?')">
        <button type="submit" class="btn btn-theme-affectation">
            <i class="fa fa-play"></i> Lancer l'affectation
        </button>
    </form>
    <a href="<?= BASE_PATH ?>/affectation/export" class="btn btn-theme-outline">
        <i class="fa fa-download"></i> Exporter PDF
    </a>
</div>

<div class="panel panel-default dash-panel dash-panel--affectation page-table-panel">
    <div class="panel-heading"><i class="fa fa-users"></i> Répartition des encadrants</div>
    <div class="table-responsive">
    <table class="table table-striped table-bordered">
        <thead>
            <tr>
                <th>Professeur</th>
                <th>Spécialité</th>
                <th>Nb étudiants</th>
                <th>Étudiants encadrés</th>
            </tr>
        </thead>
        <tbody>
        <?php
        // Regrouper les étudiants par professeur
        $etudiantsParProf = [];
        foreach ($etudiants as $e) {
            if ($e['id_prof']) {
                $etudiantsParProf[$e['id_prof']][] = $e;
            }
        }

        foreach ($profs as $p):
            $liste = isset($etudiantsParProf[$p['id_prof']]) ? $etudiantsParProf[$p['id_prof']] : [];
        ?>
            <tr>
                <td><strong><?= htmlspecialchars($p['nom_prof'] . ' ' . $p['prenom_prof']) ?></strong></td>
                <td><span class="label label-theme-blue"><?= htmlspecialchars($p['specialite']) ?></span></td>
                <td><span class="label label-theme-green"><?= count($liste) ?></span></td>
                <td>
                    <?php if (empty($liste)): ?>
                        <span class="text-muted">—</span>
                    <?php else: ?>
                        <?php foreach ($liste as $e): ?>
                            <span class="label label-theme-cyan">
                                <?= htmlspecialchars($e['nom_etud'] . ' ' . $e['prenom_etud']) ?>
                                (<?= htmlspecialchars($e['filiere']) ?>)
                            </span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($profs)): ?>
            <tr><td colspan="4" class="text-center text-muted">Aucun professeur importé — commencez par la page Import</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
