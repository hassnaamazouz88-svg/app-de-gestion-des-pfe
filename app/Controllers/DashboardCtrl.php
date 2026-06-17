<?php
declare(strict_types=1);

class DashboardCtrl
{
    public function index(): void
    {
        SessionStore::init();
        $svc   = new StatisticsSvc();
        $stats = [
            'nb_etudiants_par_prof'     => $svc->getNbEtudiantsParProf(),
            'nb_soutenances_par_prof'    => $svc->getNbSoutenancesParProf(),
            'nb_soutenances_par_filiere' => $svc->getNbSoutenancesParFiliere(),
            'nb_soutenances_par_jour'    => $svc->getNbSoutenancesParJour(),
            'taux_planification'         => $svc->getTauxPlanification(),
            'distribution_langue'        => $svc->getDistributionLangue(),
            'nb_total_etudiants'         => count(SessionStore::getEtudiants()),
            'nb_total_profs'             => count(SessionStore::getProfesseurs()),
            'nb_total_soutenances'       => count(SessionStore::getSoutenances()),
        ];
        require_once __DIR__ . '/../../views/dashboard.view.php';
    }
}
