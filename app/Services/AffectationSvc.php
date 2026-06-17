<?php
declare(strict_types=1);

class AffectationSvc
{
    public function affecter(): array
    {
        $resultat = ['affectes' => 0, 'echecs' => []];

        $sansEncadrant = SessionStore::getEtudiantsSansEncadrant();
        $profs         = SessionStore::getProfesseurs();

        if (empty($profs)) {
            $resultat['echecs'][] = 'Aucun professeur importé.';
            return $resultat;
        }
        if (empty($sansEncadrant)) {
            return $resultat;
        }

        // Quotas équitables : floor(N/P) ou floor(N/P)+1
        $nbEtud  = count($sansEncadrant);
        $nbProfs = count($profs);
        $base    = intdiv($nbEtud, $nbProfs);
        $reste   = $nbEtud % $nbProfs;

        $quotas = [];
        foreach ($profs as $idx => $p) {
            $quotas[$p['id_prof']] = $base + ($idx < $reste ? 1 : 0);
        }

        // Charge actuelle
        $chargeActuelle = array_fill_keys(array_column($profs, 'id_prof'), 0);
        foreach (SessionStore::getEtudiants() as $e) {
            if ($e['id_prof'] !== null && isset($chargeActuelle[$e['id_prof']])) {
                $chargeActuelle[$e['id_prof']]++;
            }
        }

        // Priorité langue anglais
        $anglais = array_filter($sansEncadrant, fn($e) => trim((string)$e['langue_pfe']) === LANGUE_ANGLAIS);
        $autres  = array_filter($sansEncadrant, fn($e) => trim((string)$e['langue_pfe']) !== LANGUE_ANGLAIS);

        foreach (array_merge(array_values($anglais), array_values($autres)) as $e) {
            $estAnglais = trim((string)$e['langue_pfe']) === LANGUE_ANGLAIS;
            $prof = $this->choisirProf($profs, $chargeActuelle, $quotas, $estAnglais);

            if ($prof !== null) {
                SessionStore::setEncadrant($e['id_etud'], $prof['id_prof']);
                $chargeActuelle[$prof['id_prof']]++;
                $resultat['affectes']++;
            } else {
                $resultat['echecs'][] = $e['nom_etud'] . ' ' . $e['prenom_etud'] . ' : aucun professeur disponible.';
            }
        }
        return $resultat;
    }

    private function choisirProf(array $profs, array $charge, array $quotas, bool $estAnglais): ?array
    {
        if ($estAnglais) {
            $anglais = array_filter($profs, fn($p) => trim($p['specialite']) === SPECIALITE_ANGLAIS);
            $c = $this->moinCharge($anglais, $charge, $quotas);
            if ($c) return $c;
            $info = array_filter($profs, fn($p) => trim($p['specialite']) === SPECIALITE_INFORMATIQUE);
            $c = $this->moinCharge($info, $charge, $quotas);
            if ($c) return $c;
        }
        return $this->moinCharge($profs, $charge, $quotas);
    }

    private function moinCharge(array $profs, array $charge, array $quotas): ?array
    {
        $best = null; $min = PHP_INT_MAX;
        foreach ($profs as $p) {
            $id = $p['id_prof'];
            $c  = $charge[$id] ?? 0;
            $q  = $quotas[$id] ?? 0;
            if ($c < $q && $c < $min) { $min = $c; $best = $p; }
        }
        return $best;
    }

    public function getResultat(): array
    {
        $profs    = SessionStore::getProfesseursAvecNbEtudiants();
        $etudiants= SessionStore::getEtudiants();
        $resultat = [];
        foreach ($profs as $p) {
            $resultat[$p['id_prof']] = ['prof' => $p, 'etudiants' => []];
        }
        foreach ($etudiants as $e) {
            if ($e['id_prof'] !== null && isset($resultat[$e['id_prof']])) {
                $resultat[$e['id_prof']]['etudiants'][] = $e;
            }
        }
        return $resultat;
    }
}
