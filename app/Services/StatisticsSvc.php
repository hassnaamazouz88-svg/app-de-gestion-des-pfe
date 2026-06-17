<?php
declare(strict_types=1);

class StatisticsSvc
{
    public function getNbEtudiantsParProf(): array
    {
        $data = SessionStore::getProfesseursAvecNbEtudiants();
        usort($data, fn($a, $b) => $b['nb_etudiants'] <=> $a['nb_etudiants']);
        return $data;
    }

    public function getNbSoutenancesParProf(): array
    {
        $profs  = SessionStore::getProfesseurs();
        $result = [];
        foreach ($profs as $p) {
            $nb = count(SessionStore::getSoutenancesParProf($p['id_prof']));
            $result[] = array_merge($p, ['nb_soutenances' => $nb]);
        }
        usort($result, fn($a, $b) => $b['nb_soutenances'] <=> $a['nb_soutenances']);
        return $result;
    }

    public function getNbSoutenancesParFiliere(): array
    {
        $map = [];
        foreach (SessionStore::getSoutenances() as $s) {
            $f = $s['filiere'] ?? 'N/A';
            $map[$f] = ($map[$f] ?? 0) + 1;
        }
        $result = [];
        foreach ($map as $filiere => $nb) {
            $result[] = ['filiere' => $filiere, 'nb_soutenances' => $nb];
        }
        usort($result, fn($a, $b) => $b['nb_soutenances'] <=> $a['nb_soutenances']);
        return $result;
    }

    public function getNbSoutenancesParJour(): array
    {
        $map = [];
        foreach (SessionStore::getSoutenances() as $s) {
            $d = $s['date_cren'];
            $map[$d] = ($map[$d] ?? 0) + 1;
        }
        ksort($map);
        $result = [];
        foreach ($map as $date => $nb) {
            $result[] = ['date_cren' => $date, 'nb_soutenances' => $nb];
        }
        return $result;
    }

    public function getTauxPlanification(): float
    {
        $total = count(SessionStore::getEtudiants());
        if ($total === 0) return 0.0;
        $planifies = count(SessionStore::getSoutenances());
        return round($planifies / $total * 100, 2);
    }

    public function getDistributionLangue(): array
    {
        $map = [];
        foreach (SessionStore::getEtudiants() as $e) {
            $l = trim((string)($e['langue_pfe'] ?? ''));
            $k = $l !== '' ? $l : 'Non défini';
            $map[$k] = ($map[$k] ?? 0) + 1;
        }
        $result = [];
        foreach ($map as $langue => $nb) {
            $result[] = ['langue_pfe' => $langue, 'nb' => $nb];
        }
        usort($result, fn($a, $b) => $b['nb'] <=> $a['nb']);
        return $result;
    }
}
