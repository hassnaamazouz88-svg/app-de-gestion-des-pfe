<?php
$pageTitle       = 'Tableau de bord';
$pageDescription = 'Vue d\'ensemble des soutenances, de la charge encadrante et des indicateurs clés.';
include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/navbar.php';
include __DIR__ . '/partials/main_open.php';

$tauxPlanif = (float) $stats['taux_planification'];
$tauxClass  = $tauxPlanif >= 80 ? 'dash-kpi--ok' : ($tauxPlanif >= 50 ? 'dash-kpi--warn' : 'dash-kpi--low');

$soutenancesParProf = array_column($stats['nb_soutenances_par_prof'], 'nb_soutenances', 'id_prof');

$maxSoutenancesJour = 1;
foreach ($stats['nb_soutenances_par_jour'] as $row) {
    $maxSoutenancesJour = max($maxSoutenancesJour, (int) $row['nb_soutenances']);
}

$maxSoutenancesFiliere = 1;
foreach ($stats['nb_soutenances_par_filiere'] as $row) {
    $maxSoutenancesFiliere = max($maxSoutenancesFiliere, (int) $row['nb_soutenances']);
}

$workflow = [
    ['num' => 1, 'lien' => '/import',       'label' => 'Import',       'class' => 'dash-step--1', 'icone' => 'fa-upload'],
    ['num' => 2, 'lien' => '/affectation',  'label' => 'Affectation',  'class' => 'dash-step--2', 'icone' => 'fa-users'],
    ['num' => 3, 'lien' => '/planning',     'label' => 'Planning',     'class' => 'dash-step--3', 'icone' => 'fa-calendar'],
    ['num' => 4, 'lien' => '/pv',           'label' => 'PV',           'class' => 'dash-step--4', 'icone' => 'fa-file-pdf-o'],
    ['num' => 5, 'lien' => '/verification', 'label' => 'Vérification', 'class' => 'dash-step--5', 'icone' => 'fa-check-circle'],
];
?>

<div class="row">
    <div class="col-sm-6 col-md-3">
        <div class="panel dash-kpi dash-kpi--etudiants">
            <div class="panel-body text-center">
                <i class="fa fa-graduation-cap dash-kpi-icon"></i>
                <p>Étudiants</p>
                <h3><?= (int) $stats['nb_total_etudiants'] ?></h3>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="panel dash-kpi dash-kpi--profs">
            <div class="panel-body text-center">
                <i class="fa fa-users dash-kpi-icon"></i>
                <p>Professeurs</p>
                <h3><?= (int) $stats['nb_total_profs'] ?></h3>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="panel dash-kpi dash-kpi--soutenances">
            <div class="panel-body text-center">
                <i class="fa fa-calendar dash-kpi-icon"></i>
                <p>Soutenances planifiées</p>
                <h3><?= (int) $stats['nb_total_soutenances'] ?></h3>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="panel dash-kpi dash-kpi--taux <?= $tauxClass ?>">
            <div class="panel-body text-center">
                <i class="fa fa-pie-chart dash-kpi-icon"></i>
                <p>Taux planification</p>
                <h3><?= htmlspecialchars((string) $stats['taux_planification']) ?>%</h3>
                <div class="dash-kpi-progress">
                    <div class="dash-kpi-progress-bar" style="width: <?= min(100, max(0, $tauxPlanif)) ?>%;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="dash-workflow">
    <p class="dash-workflow-title"><i class="fa fa-sitemap"></i> Workflow en 5 étapes</p>
    <div class="dash-workflow-steps">
        <?php foreach ($workflow as $step): ?>
            <a href="<?= BASE_PATH . $step['lien'] ?>" class="dash-step <?= $step['class'] ?>">
                <span class="dash-step-num"><?= (int) $step['num'] ?></span>
                <i class="fa <?= $step['icone'] ?>"></i>
                <?= htmlspecialchars($step['label']) ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="panel panel-default dash-panel dash-panel--charge">
            <div class="panel-heading"><i class="fa fa-briefcase"></i> Charge par professeur</div>
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>Professeur</th>
                            <th>Spécialité</th>
                            <th>Étudiants</th>
                            <th>Jurys</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($stats['nb_etudiants_par_prof'] as $row):
                        $nbEtud = (int) $row['nb_etudiants'];
                        // Couleur basée sur l'écart par rapport à la moyenne équitable
                        $_allNb      = array_column($stats['nb_etudiants_par_prof'], 'nb_etudiants');
                        $_nbProfs    = count($_allNb);
                        $_total      = array_sum($_allNb);
                        $_base       = $_nbProfs > 0 ? intdiv((int)$_total, $_nbProfs) : 0;
                        $_reste      = $_nbProfs > 0 ? (int)$_total % $_nbProfs : 0;
                        $_acceptable = ($nbEtud === $_base) || ($_reste > 0 && $nbEtud === $_base + 1);
                        $_ecart1     = abs($nbEtud - $_base);
                        if ($_acceptable) {
                            $chargeClass = 'badge-charge-ok';
                        } elseif ($_ecart1 === 1) {
                            $chargeClass = 'badge-charge-warn';
                        } else {
                            $chargeClass = 'badge-charge-danger';
                        }

                        $spec = mb_strtolower(trim((string) $row['specialite']));
                        if ($spec === mb_strtolower(SPECIALITE_INFORMATIQUE)) {
                            $specClass = 'badge-spec-info';
                        } elseif ($spec === mb_strtolower(SPECIALITE_ANGLAIS)) {
                            $specClass = 'badge-spec-anglais';
                        } else {
                            $specClass = 'badge-spec-autre';
                        }

                        $nbJurys = (int) ($soutenancesParProf[$row['id_prof']] ?? 0);
                    ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($row['nom_prof'] . ' ' . $row['prenom_prof']) ?></strong></td>
                            <td><span class="label <?= $specClass ?>"><?= htmlspecialchars($row['specialite']) ?></span></td>
                            <td><span class="label <?= $chargeClass ?>"><?= $nbEtud ?></span></td>
                            <td><span class="label label-primary"><?= $nbJurys ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($stats['nb_etudiants_par_prof'])): ?>
                        <tr><td colspan="4" class="text-center text-muted">Aucune donnée</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="panel panel-default dash-panel dash-panel--filiere">
            <div class="panel-heading"><i class="fa fa-university"></i> Soutenances par filière</div>
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead><tr><th>Filière</th><th>Nombre</th></tr></thead>
                    <tbody>
                    <?php foreach ($stats['nb_soutenances_par_filiere'] as $i => $row):
                        $nb = (int) $row['nb_soutenances'];
                        $pct = round(($nb / $maxSoutenancesFiliere) * 100);
                    ?>
                        <tr>
                            <td>
                                <span class="label badge-filiere-<?= $i % 6 ?>"><?= htmlspecialchars($row['filiere'] ?? 'N/A') ?></span>
                            </td>
                            <td class="dash-bar-cell">
                                <div class="dash-bar-wrap">
                                    <div class="dash-bar-track">
                                        <div class="dash-bar-fill dash-bar-fill--green" style="width: <?= $pct ?>%;"></div>
                                    </div>
                                    <span class="dash-bar-value"><?= $nb ?></span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($stats['nb_soutenances_par_filiere'])): ?>
                        <tr><td colspan="2" class="text-center text-muted">Aucune soutenance planifiée</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="panel panel-default dash-panel dash-panel--jour">
            <div class="panel-heading"><i class="fa fa-calendar"></i> Soutenances par jour</div>
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead><tr><th>Date</th><th>Nb soutenances</th></tr></thead>
                    <tbody>
                    <?php foreach ($stats['nb_soutenances_par_jour'] as $row):
                        $nb = (int) $row['nb_soutenances'];
                        $pct = round(($nb / $maxSoutenancesJour) * 100);
                    ?>
                        <tr>
                            <td><i class="fa fa-clock-o text-info"></i> <?= htmlspecialchars($row['date_cren']) ?></td>
                            <td class="dash-bar-cell">
                                <div class="dash-bar-wrap">
                                    <div class="dash-bar-track">
                                        <div class="dash-bar-fill dash-bar-fill--cyan" style="width: <?= $pct ?>%;"></div>
                                    </div>
                                    <span class="dash-bar-value"><?= $nb ?></span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($stats['nb_soutenances_par_jour'])): ?>
                        <tr><td colspan="2" class="text-center text-muted">Aucune soutenance planifiée</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="panel panel-default dash-panel dash-panel--langue">
            <div class="panel-heading"><i class="fa fa-globe"></i> Distribution par langue PFE</div>
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead><tr><th>Langue</th><th>Nb étudiants</th></tr></thead>
                    <tbody>
                    <?php
                    $maxLangue = 1;
                    foreach ($stats['distribution_langue'] as $row) {
                        $maxLangue = max($maxLangue, (int) $row['nb']);
                    }
                    foreach ($stats['distribution_langue'] as $row):
                        $langue = trim((string) ($row['langue_pfe'] ?? 'Non défini'));
                        $langueLower = mb_strtolower($langue);
                        if ($langueLower === 'anglais' || $langueLower === 'english') {
                            $langClass = 'badge-lang-en';
                        } elseif ($langueLower === 'français' || $langueLower === 'francais' || $langueLower === 'french') {
                            $langClass = 'badge-lang-fr';
                        } else {
                            $langClass = 'badge-lang-nd';
                        }
                        $nb = (int) $row['nb'];
                        $pct = round(($nb / $maxLangue) * 100);
                    ?>
                        <tr>
                            <td><span class="label <?= $langClass ?>"><?= htmlspecialchars($langue) ?></span></td>
                            <td class="dash-bar-cell">
                                <div class="dash-bar-wrap">
                                    <div class="dash-bar-track">
                                        <div class="dash-bar-fill dash-bar-fill--orange" style="width: <?= $pct ?>%;"></div>
                                    </div>
                                    <span class="dash-bar-value"><?= $nb ?></span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($stats['distribution_langue'])): ?>
                        <tr><td colspan="2" class="text-center text-muted">Aucune donnée</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
