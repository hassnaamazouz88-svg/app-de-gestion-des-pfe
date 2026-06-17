<?php
$pageTitle       = 'Importation';
$pageDescription = 'Importez toutes les données (étudiants, professeurs, salles) via un seul fichier Excel multi-feuilles.';
include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/navbar.php';
include __DIR__ . '/partials/main_open.php';

$compteurs = $compteurs ?? ['etudiants' => 0, 'profs' => 0, 'salles' => 0];

// Flash session
if (!empty($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $flashType = $flash['type'] === 'error' ? 'danger' : $flash['type'];
    echo '<div class="alert alert-' . htmlspecialchars($flashType) . '">' . htmlspecialchars($flash['message']) . '</div>';
}
?>

<?php if (!empty($messages)): ?>
    <?php foreach ($messages as $msg): ?>
        <?php $type = $msg['type'] === 'error' ? 'danger' : $msg['type']; ?>
        <div class="alert alert-<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($msg['text']) ?></div>
    <?php endforeach; ?>
<?php endif; ?>

<div class="alert alert-info page-info">
    <i class="fa fa-info-circle"></i>
    <strong>Format attendu :</strong> un seul fichier <code>.xlsx</code> contenant <strong>3 feuilles</strong> :
    <ul style="margin: 6px 0 0 0;">
        <li><strong>Feuille 1 – Étudiants</strong> : colonnes <code>nom_etud</code>, <code>prenom_etud</code>, <code>filiere</code> (+ optionnel : <code>email</code>, <code>sujet_pfe</code>)</li>
        <li><strong>Feuille 2 – Professeurs</strong> : colonnes <code>nom_prof</code>, <code>prenom_prof</code>, <code>specialite</code></li>
        <li><strong>Feuille 3 – Salles</strong> : colonne <code>num_salle</code></li>
    </ul>
    Les feuilles peuvent être nommées librement (ex. "Etudiants", "Profs", "Salles") ou identifiées par position.
</div>

<div class="page-stats">
    <strong>Données en base :</strong>
    <?= (int) $compteurs['etudiants'] ?> étudiant(s) ·
    <?= (int) $compteurs['profs'] ?> professeur(s) ·
    <?= (int) $compteurs['salles'] ?> salle(s)
</div>

<!-- ── Formulaire d'import unique ───────────────────────────── -->
<div class="panel panel-default dash-panel dash-panel--import-etudiants">
    <div class="panel-heading">
        <i class="fa fa-upload"></i> <strong>Importer le fichier Excel</strong>
    </div>
    <div class="panel-body">
        <form action="<?= BASE_PATH ?>/import/fichier" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="fichier_import">Fichier Excel multi-feuilles (.xlsx / .xls)</label>
                <input type="file" id="fichier_import" name="fichier_import"
                       class="form-control" accept=".xlsx,.xls" required>
            </div>
            <button type="submit" class="btn btn-theme-import btn-block">
                <i class="fa fa-upload"></i> Importer étudiants + professeurs + salles
            </button>
        </form>
    </div>
</div>

<!-- ── Suppression globale ──────────────────────────────────── -->
<?php if ($compteurs['etudiants'] > 0 || $compteurs['profs'] > 0 || $compteurs['salles'] > 0): ?>
<div class="panel panel-default dash-panel">
    <div class="panel-heading">
        <i class="fa fa-trash"></i> <strong>Supprimer les données importées</strong>
    </div>
    <div class="panel-body">
        <p class="text-muted" style="margin-bottom:12px">
            <?= (int) $compteurs['etudiants'] ?> étudiant(s) ·
            <?= (int) $compteurs['profs'] ?> professeur(s) ·
            <?= (int) $compteurs['salles'] ?> salle(s)
        </p>
        <form action="<?= BASE_PATH ?>/import/supprimer" method="post"
              onsubmit="return confirm('Supprimer toutes les données importées (étudiants, professeurs, salles) ? L\'affectation et le planning seront réinitialisés.');">
            <button type="submit" class="btn btn-danger">
                <i class="fa fa-trash"></i> Supprimer toutes les données
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/partials/footer.php'; ?>
