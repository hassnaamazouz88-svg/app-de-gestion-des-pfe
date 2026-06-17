<?php
declare(strict_types=1);

class PlanningSvc
{
    /**
     * @param array<int,string> $heuresDebut Liste d'heures "HH:MM"
     * @param array<int,int> $sallesSelectionnees Liste de numéros de salle
     */
    public function planifier(string $dateDebut, string $dateFin, array $heuresDebut, array $sallesSelectionnees): array
    {
        $resultat = ['planifies' => 0, 'echecs' => [], 'avertissements' => []];

        SessionStore::resetPlanning();

        $aPlanifier = array_values(array_filter(
            SessionStore::getEtudiantsSansSoutenance(),
            fn($e) => $e['id_prof'] !== null
        ));

        if (empty($aPlanifier)) {
            $resultat['avertissements'][] = 'Tous les étudiants encadrés ont déjà une soutenance.';
            return $resultat;
        }

        $toutesSalles = SessionStore::getSalles();
        if (empty($toutesSalles)) {
            throw new \InvalidArgumentException('Importez des salles avant de générer le planning.');
        }
        $salles = $sallesSelectionnees !== [] ? array_values(array_intersect($toutesSalles, $sallesSelectionnees)) : $toutesSalles;
        if ($salles === []) {
            throw new \InvalidArgumentException('Aucune salle sélectionnée.');
        }
        if ($heuresDebut === []) {
            throw new \InvalidArgumentException('Aucune plage horaire sélectionnée.');
        }

        SessionStore::genererCreneauxPlages($dateDebut, $dateFin, $heuresDebut);

        $creneaux = SessionStore::getCreneaux();
        if ($creneaux === []) {
            throw new \InvalidArgumentException('Aucun créneau généré (vérifiez dates et plages horaires).');
        }

        $groupes = $this->grouperParEncadrant($aPlanifier);
        $echecs = [];

        foreach ($groupes as $etudiants) {
            foreach ($etudiants as $etudiant) {
                if ($this->planifierEtudiant($etudiant, $creneaux, $salles)) {
                    $resultat['planifies']++;
                    $creneaux = SessionStore::getCreneaux();
                } else {
                    $echecs[] = $etudiant;
                }
            }
        }

        // Nouvelle tentative avec l'ordre des créneaux inversé
        if ($echecs !== []) {
            $creneauxInv = array_reverse(SessionStore::getCreneaux());
            $encore = [];
            foreach ($echecs as $etudiant) {
                if ($this->planifierEtudiant($etudiant, $creneauxInv, $salles)) {
                    $resultat['planifies']++;
                } else {
                    $encore[] = $etudiant;
                }
            }
            $echecs = $encore;
        }

        foreach ($echecs as $e) {
            $resultat['echecs'][] = $e['nom_etud'] . ' ' . $e['prenom_etud'] . ' : aucun créneau compatible trouvé.';
        }

        return $resultat;
    }

    /**
     * @param array<int,array<string,mixed>> $etudiants
     * @return array<int,array<int,array<string,mixed>>>
     */
    private function grouperParEncadrant(array $etudiants): array
    {
        $groupes = [];
        foreach ($etudiants as $e) {
            $groupes[$e['id_prof']][] = $e;
        }
        return $groupes;
    }

    /**
     * @param array<int,array<string,mixed>> $creneaux
     * @param array<int,int> $sallesAutorisees
     */
    private function planifierEtudiant(array $etudiant, array $creneaux, array $sallesAutorisees): bool
    {
        $encadrant = SessionStore::getProfesseurById((int) $etudiant['id_prof']);
        if (!$encadrant) {
            return false;
        }

        foreach ($creneaux as $cren) {
            $idCren = $cren['id_cren'];

            if (!SessionStore::isProfDisponible($encadrant['id_prof'], $idCren)) {
                continue;
            }
            if (!SessionStore::hasRepos($encadrant['id_prof'], $idCren)) {
                continue;
            }

            $sallesLibres = SessionStore::getSallesDisponiblesDans($idCren, $sallesAutorisees);
            if ($sallesLibres === []) {
                continue;
            }

            $jury = $this->composerJury($etudiant, $encadrant, $idCren);
            if ($jury === null) {
                continue;
            }

            $idStnc = SessionStore::ajouterSoutenance([
                'id_etud'   => $etudiant['id_etud'],
                'id_cren'   => $idCren,
                'num_salle' => $sallesLibres[0],
            ]);
            SessionStore::ajouterJury($idStnc, $encadrant['id_prof'], 'encadrant');
            foreach ($jury as $jProf) {
                SessionStore::ajouterJury($idStnc, $jProf['id_prof'], 'jury');
            }
            return true;
        }

        return false;
    }

    public function composerJury(array $etudiant, array $encadrant, int $idCren): ?array
    {
        $langueAnglais    = $this->estAnglais($etudiant['langue_pfe'] ?? null);
        $encadrantAnglais = mb_strtolower(trim($encadrant['specialite'])) === mb_strtolower(SPECIALITE_ANGLAIS);
        $exclusions       = [$encadrant['id_prof']];

        if ($langueAnglais && !$encadrantAnglais) {
            return $this->juryCas1($idCren, $exclusions);
        }
        if ($langueAnglais && $encadrantAnglais) {
            return $this->juryCas2($idCren, $exclusions, $encadrant);
        }
        return $this->juryCas3($idCren, $exclusions, $encadrant);
    }

    private function juryCas1(int $idCren, array $excl): ?array
    {
        foreach ($this->profsDispo($idCren, $excl, SPECIALITE_ANGLAIS) as $a) {
            $ex2 = array_merge($excl, [$a['id_prof']]);
            foreach ($this->profsDispo($idCren, $ex2, SPECIALITE_INFORMATIQUE) as $info) {
                return [$a, $info];
            }
        }
        return null;
    }

    private function juryCas2(int $idCren, array $excl, array $enc): ?array
    {
        $infos = $this->profsDispo($idCren, $excl, SPECIALITE_INFORMATIQUE);
        $n = count($infos);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                if ($this->nbInfo([$enc, $infos[$i], $infos[$j]]) >= MIN_INFORMATICIENS_JURY) {
                    return [$infos[$i], $infos[$j]];
                }
            }
        }
        return null;
    }

    private function juryCas3(int $idCren, array $excl, array $enc): ?array
    {
        $candidats = $this->profsDispo($idCren, $excl);
        usort($candidats, fn($a, $b) =>
            (mb_strtolower(trim($a['specialite'])) === mb_strtolower(SPECIALITE_ANGLAIS) ? 1 : 0)
            - (mb_strtolower(trim($b['specialite'])) === mb_strtolower(SPECIALITE_ANGLAIS) ? 1 : 0)
        );
        $n = count($candidats);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                if ($this->nbInfo([$enc, $candidats[$i], $candidats[$j]]) >= MIN_INFORMATICIENS_JURY) {
                    return [$candidats[$i], $candidats[$j]];
                }
            }
        }
        return null;
    }

    /**
     * @param array<int,int> $excl
     * @return array<int,array<string,mixed>>
     */
    private function profsDispo(int $idCren, array $excl, ?string $spec = null): array
    {
        $profs = $spec ? SessionStore::getProfesseursBySpecialite($spec) : SessionStore::getProfesseurs();
        return array_values(array_filter($profs, function ($p) use ($idCren, $excl) {
            $id = $p['id_prof'];
            return !in_array($id, $excl, true)
                && SessionStore::isProfDisponible($id, $idCren)
                && SessionStore::hasRepos($id, $idCren);
        }));
    }

    /**
     * @param array<int,array<string,mixed>> $membres
     */
    private function nbInfo(array $membres): int
    {
        return count(array_filter($membres, fn($m) =>
            mb_strtolower(trim($m['specialite'])) === mb_strtolower(SPECIALITE_INFORMATIQUE)
        ));
    }

    private function estAnglais(?string $langue): bool
    {
        $l = mb_strtolower(trim((string) $langue));
        return $l === 'anglais' || $l === 'english';
    }
}
