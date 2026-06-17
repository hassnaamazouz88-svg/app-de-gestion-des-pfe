<?php
declare(strict_types=1);

class AffectationCtrl
{
    public function index(): void
    {
        SessionStore::init();
        $resultat = (new AffectationSvc())->getResultat();
        $etudiants = SessionStore::getEtudiants();
        $profs = SessionStore::getProfesseurs();
        require_once __DIR__ . '/../../views/affectation.view.php';
    }

    public function lancer(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: '.BASE_PATH.'/affectation'); exit; }
        SessionStore::init();
        $svc      = new AffectationSvc();
        $resultat_action = $svc->affecter();
        $resultat = $svc->getResultat();
        $etudiants = SessionStore::getEtudiants();
        $profs = SessionStore::getProfesseurs();
        require_once __DIR__ . '/../../views/affectation.view.php';
    }

    public function export(): void
    {
        SessionStore::init();
        $svc      = new PdfAffectationSvc();
        $filepath = $svc->generer();
        $this->telecharger($filepath);
    }

    private function telecharger(string $filepath): void
    {
        if (!file_exists($filepath)) { http_response_code(404); echo 'Fichier introuvable.'; return; }
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="'.basename($filepath).'"');
        header('Content-Length: '.(string)filesize($filepath));
        readfile($filepath); exit;
    }
}
