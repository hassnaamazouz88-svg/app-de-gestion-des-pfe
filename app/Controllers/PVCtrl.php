<?php
declare(strict_types=1);

class PVCtrl
{
    public function index(): void
    {
        SessionStore::init();
        $soutenances = SessionStore::getSoutenances();
        require_once __DIR__ . '/../../views/pv.view.php';
    }

    public function generer(): void
    {
        SessionStore::init();
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) { http_response_code(400); echo 'ID invalide.'; return; }
        try {
            $filepath = (new PdfPVSvc())->genererUn($id);
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="'.basename($filepath).'"');
            header('Content-Length: '.(string)filesize($filepath));
            readfile($filepath); exit;
        } catch (\Throwable $e) {
            http_response_code(500); echo htmlspecialchars($e->getMessage());
        }
    }

    public function genererTous(): void
    {
        SessionStore::init();
        $fichiers = (new PdfPVSvc())->genererTous();
        if (empty($fichiers)) { echo 'Aucune soutenance planifiée.'; return; }
        // Créer un ZIP
        $zipPath = sys_get_temp_dir() . '/pv_tous_' . date('Ymd_His') . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE) === true) {
            foreach ($fichiers as $f) { if (file_exists($f)) $zip->addFile($f, basename($f)); }
            $zip->close();
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="PV_tous.zip"');
            header('Content-Length: '.(string)filesize($zipPath));
            readfile($zipPath); unlink($zipPath); exit;
        }
        echo 'Erreur ZIP.';
    }
}
