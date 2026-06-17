<?php
declare(strict_types=1);

class ImportCtrl
{
    public function index(): void
    {
        SessionStore::init();
        $messages  = [];
        $compteurs = SessionStore::compter();
        require_once __DIR__ . '/../../views/import.view.php';
    }

    public function importFichier(): void
    {
        SessionStore::init();
        $messages  = [];
        $compteurs = SessionStore::compter();

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['fichier_import'])) {
            $resultats = (new ExcelImportSvc())->importFichierComplet($_FILES['fichier_import']);

            foreach ($resultats['errors'] as $err) {
                $messages[] = ['type' => 'error', 'text' => $err];
            }

            $re = $resultats['etudiants'];
            if ($re !== []) {
                $parts = [];
                if (($re['imported'] ?? 0) > 0) $parts[] = $re['imported'] . ' ajouté(s)';
                $messages[] = ['type' => ($parts !== [] && empty($re['errors'])) ? 'success' : 'info',
                    'text' => 'Étudiants : ' . ($parts ? implode(', ', $parts) . '.' : 'Aucun importé.')];
                foreach ($re['warnings'] ?? [] as $w) $messages[] = ['type' => 'info', 'text' => '· ' . $w];
                foreach ($re['errors']   ?? [] as $e) $messages[] = ['type' => 'error', 'text' => '· ' . $e];
            }

            $rp = $resultats['professeurs'];
            if ($rp !== []) {
                $messages[] = ['type' => (($rp['imported'] ?? 0) > 0 && empty($rp['errors'])) ? 'success' : 'info',
                    'text' => 'Professeurs : ' . ($rp['imported'] ?? 0) . ' importé(s).'];
                foreach ($rp['errors'] ?? [] as $e) $messages[] = ['type' => 'error', 'text' => '· ' . $e];
            }

            $rs = $resultats['salles'];
            if ($rs !== []) {
                $parts = [];
                if (($rs['imported'] ?? 0) > 0) $parts[] = $rs['imported'] . ' importée(s)';
                if (($rs['skipped']  ?? 0) > 0) $parts[] = $rs['skipped']  . ' ignorée(s)';
                $messages[] = ['type' => ($parts !== [] && empty($rs['errors'])) ? 'success' : 'info',
                    'text' => 'Salles : ' . ($parts ? implode(', ', $parts) . '.' : 'Aucune importée.')];
                foreach ($rs['warnings'] ?? [] as $w) $messages[] = ['type' => 'info', 'text' => '· ' . $w];
                foreach ($rs['errors']   ?? [] as $e) $messages[] = ['type' => 'error', 'text' => '· ' . $e];
            }

            $compteurs = SessionStore::compter();
        }

        require_once __DIR__ . '/../../views/import.view.php';
    }

    public function supprimerDonnees(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_PATH . '/import');
            exit;
        }
        SessionStore::init();
        $compteurs = SessionStore::compter();
        $total = (int) $compteurs['etudiants'] + (int) $compteurs['profs'] + (int) $compteurs['salles'];
        SessionStore::reset();
        unset($_SESSION['planning_config']);
        $_SESSION['flash'] = [
            'type'    => 'success',
            'message' => "Toutes les données importées ont été supprimées ($total élément(s)). Affectation et planning réinitialisés.",
        ];
        header('Location: ' . BASE_PATH . '/import');
        exit;
    }
}
