<?php
declare(strict_types=1);

class VerifCtrl
{
    public function index(): void
    {
        SessionStore::init();
        $resultats = (new VerificationSvc())->verifierTout();
        require_once __DIR__ . '/../../views/verification.view.php';
    }
}
