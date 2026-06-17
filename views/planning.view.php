<?php
$pageTitle       = 'Planning';
$pageDescription = 'Génération automatique du planning et consultation des soutenances planifiées.';
include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/navbar.php';
include __DIR__ . '/partials/main_open.php';

$analyseEffectuee = $analyseEffectuee ?? false;
$sallesDisponibles = $sallesDisponibles ?? [];
$sallesSelectionnees = $sallesSelectionnees ?? null;
$soutenances = $soutenances ?? [];
?>

<?php if (!empty($_SESSION['flash'])): ?>
    <?php $flash = $_SESSION['flash']; unset($_SESSION['flash']); ?>
    <?php $flashType = ($flash['type'] ?? 'info') === 'error' ? 'danger' : ($flash['type'] ?? 'info'); ?>
    <div class="alert alert-<?= htmlspecialchars($flashType) ?>"><?= htmlspecialchars($flash['message'] ?? '') ?></div>
<?php endif; ?>

<?php if (!empty($erreur)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($erreur) ?></div>
<?php endif; ?>

<?php if (!empty($messages)): ?>
    <?php foreach ($messages as $msg): ?>
        <?php
        $type = $msg['type'];
        if ($type === 'error') {
            $type = 'danger';
        }
        ?>
        <div class="alert alert-<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($msg['text']) ?></div>
    <?php endforeach; ?>
<?php endif; ?>

<div class="panel panel-default dash-panel dash-panel--planning">
    <div class="panel-heading"><i class="fa fa-calendar"></i> Paramètres de la session</div>
    <div class="panel-body">
        <form action="<?= BASE_PATH ?>/planning/analyser" method="post" id="formAnalyse">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group" style="width:100%">
                        <label>Date début</label>
                        <input type="date" name="date_debut_session" id="date_debut_session" class="form-control"
                               value="<?= htmlspecialchars($dateSessionDefaut ?? date('Y-m-d')) ?>" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group" style="width:100%">
                        <label>Date fin</label>
                        <input type="date" name="date_fin_session" id="date_fin_session" class="form-control"
                               value="<?= htmlspecialchars($dateFinSessionDefaut ?? '') ?>"
                               min="<?= htmlspecialchars($dateSessionDefaut ?? date('Y-m-d')) ?>"
                               required>
                        <p class="help-block" id="nbJoursSession" style="margin:6px 0 0 0;color:#555"></p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group" style="width:100%">
                        <label>Plages horaires (heures de début)</label>
                        <input type="text" name="plages_horaires" class="form-control"
                               value="<?= htmlspecialchars($heuresDebutDefaut ?? '') ?>"
                               placeholder="ex: 09:00, 10:00, 11:00, 14:00" required>
                        <p class="help-block" style="margin:6px 0 0 0">Durée fixe : <strong>1h</strong> par soutenance.</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <label>Salles <small class="text-muted">(aucune cochée = toutes)</small></label>
                    <div style="max-height:110px;overflow:auto;border:1px solid #eee;padding:8px;border-radius:4px">
                        <?php if (empty($sallesDisponibles)): ?>
                            <div class="text-muted">Aucune salle importée (voir page Import)</div>
                        <?php else: ?>
                            <?php foreach ($sallesDisponibles as $sa): ?>
                                <?php
                                if ($sallesSelectionnees === null) {
                                    $checked = false;
                                } else {
                                    $checked = in_array($sa, $sallesSelectionnees, true);
                                }
                                ?>
                                <label style="display:inline-block;margin-right:10px">
                                    <input type="checkbox" name="salles[]" value="<?= (int) $sa ?>" <?= $checked ? 'checked' : '' ?>>
                                    Salle <?= (int) $sa ?>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div style="margin-top:10px">
                <button type="submit" class="btn btn-theme-planning">
                    <i class="fa fa-search"></i> Analyser la faisabilité
                </button>
                <a href="<?= BASE_PATH ?>/planning/export" class="btn btn-theme-outline">Exporter PDF</a>
            </div>
        </form>

        <?php if (!empty($peutReinitialiser)): ?>
        <form action="<?= BASE_PATH ?>/planning/reinitialiser" method="post" style="margin-top:10px"
              onsubmit="return confirm('Réinitialiser le planning ? Toutes les soutenances planifiées seront supprimées.');">
            <button type="submit" class="btn btn-danger">
                <i class="fa fa-refresh"></i> Initialiser le planning
            </button>
        </form>
        <?php endif; ?>

        <?php if ($analyseEffectuee && !empty($analysis)): ?>
            <?php
            $niveau = $analysis['niveau'] ?? 'ok';
            $badge = $niveau === 'bad' ? 'danger' : ($niveau === 'warn' ? 'warning' : 'success');
            $label = $niveau === 'bad' ? 'Non faisable' : ($niveau === 'warn' ? 'Faisable avec contraintes' : 'Faisable');
            ?>
            <hr>
            <div class="alert alert-<?= $badge ?>">
                <strong>Niveau de faisabilité :</strong> <?= htmlspecialchars($label) ?>
                — Capacité <?= (int) $analysis['capacite'] ?> / Besoin <?= (int) $analysis['besoin'] ?>
                — Occupation prévisionnelle <?= (int) round(($analysis['occupation'] ?? 0) * 100) ?>%
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="panel panel-default">
                        <div class="panel-heading"><strong>Statistiques</strong></div>
                        <div class="panel-body">
                            <div><strong>Étudiants à planifier :</strong> <?= (int) $analysis['besoin'] ?></div>
                            <div><strong>Jours de session :</strong> <?= (int) $analysis['jours'] ?></div>
                            <div><strong>Créneaux / jour :</strong> <?= (int) $analysis['creneaux_par_jour'] ?></div>
                            <div><strong>Créneaux totaux :</strong> <?= (int) $analysis['creneaux'] ?></div>
                            <div><strong>Salles sélectionnées :</strong> <?= (int) $analysis['salles'] ?></div>
                            <div><strong>Capacité maximale :</strong> <?= (int) $analysis['capacite'] ?></div>
                            <div><strong>Minimum estimé :</strong> <?= (int) $analysis['jours_min'] ?> jour(s) · <?= (int) $analysis['salles_min'] ?> salle(s)</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="panel panel-default">
                        <div class="panel-heading"><strong>Avertissements & recommandations</strong></div>
                        <div class="panel-body">
                            <?php $warns = $analysis['avertissements'] ?? []; ?>
                            <?php $recs  = $analysis['recommandations'] ?? []; ?>
                            <?php if (empty($warns) && empty($recs)): ?>
                                <div class="text-muted">Aucun avertissement.</div>
                            <?php else: ?>
                                <?php foreach ($warns as $w): ?>
                                    <div class="text-warning"><i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($w) ?></div>
                                <?php endforeach; ?>
                                <?php if (!empty($recs)): ?>
                                    <hr style="margin:10px 0">
                                    <?php foreach ($recs as $r): ?>
                                        <div><i class="fa fa-lightbulb-o"></i> <?= htmlspecialchars($r) ?></div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <form action="<?= BASE_PATH ?>/planning/generer" method="post"
                  onsubmit="return confirm('Générer le planning avec ces paramètres ?');">
                <button type="submit" class="btn btn-success btn-lg">
                    <i class="fa fa-play"></i> Générer le planning
                </button>
                <p class="help-block" style="margin-top:8px">
                    La génération utilise les paramètres validés par l'analyse ci-dessus.
                    Pour modifier la session, relancez une nouvelle analyse.
                </p>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="form-inline page-filters">
    <div class="form-group">
        <label>Filtrer par date</label>
        <select id="filtreDate" class="form-control" onchange="filtrerPlanning()">
            <option value="">— Toutes —</option>
            <?php
            $dates = array_unique(array_column($soutenances ?? [], 'date_cren'));
            sort($dates);
            foreach ($dates as $d):
            ?>
                <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label>Filtrer par salle</label>
        <select id="filtreSalle" class="form-control" onchange="filtrerPlanning()">
            <option value="">— Toutes —</option>
            <?php
            $salles = array_unique(array_column($soutenances ?? [], 'num_salle'));
            sort($salles);
            foreach ($salles as $sa):
            ?>
                <option value="<?= htmlspecialchars($sa) ?>"><?= htmlspecialchars($sa) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<div class="panel panel-default dash-panel dash-panel--planning page-table-panel">
    <div class="panel-heading"><i class="fa fa-list"></i> Soutenances planifiées</div>
    <div class="table-responsive">
    <table class="table table-striped table-bordered" id="tablePlanning">
        <thead>
            <tr>
                <th>Date</th>
                <th>Heure</th>
                <th>Salle</th>
                <th>Étudiant</th>
                <th>Filière</th>
                <th>Langue</th>
                <th>Encadrant</th>
                <th>Jury 1</th>
                <th>Jury 2</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach (($soutenances ?? []) as $s): ?>
            <tr data-date="<?= htmlspecialchars($s['date_cren']) ?>"
                data-salle="<?= htmlspecialchars($s['num_salle']) ?>">
                <td><?= htmlspecialchars($s['date_cren']) ?></td>
                <td><?= substr($s['heure_debut'], 0, 5) ?>–<?= substr($s['heure_fin'], 0, 5) ?></td>
                <td><span class="label label-theme-green">Salle <?= htmlspecialchars($s['num_salle']) ?></span></td>
                <td><strong><?= htmlspecialchars($s['nom_etud'] . ' ' . $s['prenom_etud']) ?></strong></td>
                <td><?= htmlspecialchars($s['filiere']) ?></td>
                <td><?= htmlspecialchars($s['langue_pfe']) ?></td>
                <td><?= htmlspecialchars(trim(($s['nom_encadrant'] ?? '') . ' ' . ($s['prenom_encadrant'] ?? ''))) ?></td>
                <td><?= htmlspecialchars(trim(($s['nom_jury1'] ?? '') . ' ' . ($s['prenom_jury1'] ?? ''))) ?></td>
                <td><?= htmlspecialchars(trim(($s['nom_jury2'] ?? '') . ' ' . ($s['prenom_jury2'] ?? ''))) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($soutenances)): ?>
            <tr><td colspan="9" class="text-center text-muted">Aucune soutenance planifiée — analysez puis générez le planning</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<script>
var debut = document.getElementById('date_debut_session');
var fin   = document.getElementById('date_fin_session');
var nbJoursEl = document.getElementById('nbJoursSession');

function joursInclusifs(debutIso, finIso) {
    if (!debutIso || !finIso) return 0;
    var t0 = new Date(debutIso + 'T12:00:00').getTime();
    var t1 = new Date(finIso + 'T12:00:00').getTime();
    if (isNaN(t0) || isNaN(t1) || t1 < t0) return 0;
    return Math.round((t1 - t0) / 86400000) + 1;
}

function syncDatesSession() {
    if (!debut || !fin) return;
    fin.min = debut.value;
    if (fin.value && fin.value < fin.min) fin.value = fin.min;

    var nb = joursInclusifs(debut.value, fin.value);
    if (nbJoursEl) {
        if (nb > 0) {
            nbJoursEl.textContent = 'Durée de la session : ' + nb + ' jour(s)';
        } else {
            nbJoursEl.textContent = 'Définissez la date de fin pour calculer la durée de la session.';
        }
    }
}

if (debut && fin) {
    debut.onchange = syncDatesSession;
    fin.onchange = syncDatesSession;
    syncDatesSession();
}

function filtrerPlanning() {
    var date  = document.getElementById('filtreDate').value;
    var salle = document.getElementById('filtreSalle').value;
    var lignes = document.querySelectorAll('#tablePlanning tbody tr');

    for (var i = 0; i < lignes.length; i++) {
        var tr = lignes[i];
        var dateOk  = !date  || tr.getAttribute('data-date')  === date;
        var salleOk = !salle || tr.getAttribute('data-salle') === salle;
        tr.style.display = (dateOk && salleOk) ? '' : 'none';
    }
}
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
