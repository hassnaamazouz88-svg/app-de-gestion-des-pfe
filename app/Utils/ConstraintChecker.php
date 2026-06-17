<?php
declare(strict_types=1);

class ConstraintChecker
{
    private Soutenance $soutenanceModel;
    private Creneau    $creneauModel;
    private Professeur $profModel;
    private Participer $partModel;

    public function __construct()
    {
        $this->soutenanceModel = new Soutenance();
        $this->creneauModel    = new Creneau();
        $this->profModel       = new Professeur();
        $this->partModel       = new Participer();
    }

    /** Vérifie qu'un professeur n'est pas déjà pris sur ce créneau. */
    public function isProfDisponible(int $idProf, int $idCren): bool
    {
        foreach ($this->partModel->getBySoutenancesProf($idProf) as $ligne) {
            if ((int) $ligne['id_cren'] === $idCren) {
                return false;
            }
        }
        return true;
    }

    /** Vérifie le repos minimum entre deux soutenances d'un même prof. */
    public function hasRepos(int $idProf, int $idCren): bool
    {
        $candidat = null;
        foreach ($this->creneauModel->getAll() as $c) {
            if ((int) $c['id_cren'] === $idCren) {
                $candidat = $c;
                break;
            }
        }
        if ($candidat === null) {
            return false;
        }

        $debutCandidat = strtotime($candidat['date_cren'] . ' ' . $candidat['heure_debut']);
        $finCandidat   = strtotime($candidat['date_cren'] . ' ' . $candidat['heure_fin']);
        $reposMin      = REPOS_MIN_PROF * 60;

        foreach ($this->soutenanceModel->getByProf($idProf) as $s) {
            $debutS = strtotime($s['date_cren'] . ' ' . $s['heure_debut']);
            $finS   = strtotime($s['date_cren'] . ' ' . $s['heure_fin']);

            if ($debutCandidat >= $finS && ($debutCandidat - $finS) < $reposMin) {
                return false;
            }
            if ($finCandidat <= $debutS && ($debutS - $finCandidat) < $reposMin) {
                return false;
            }
        }
        return true;
    }

    /** Vérifie qu'une salle est libre sur un créneau. */
    public function isSalleLibre(int|string $numSalle, int $idCren, ?int $excludeIdStnc = null): bool
    {
        foreach ($this->soutenanceModel->getByCreneau($idCren) as $s) {
            if ($excludeIdStnc !== null && (int) $s['id_stnc'] === $excludeIdStnc) {
                continue;
            }
            $salleOccupee = (int) ($s['num_salle'] ?? $s['Num_salle'] ?? 0);
            if ($salleOccupee === (int) $numSalle) {
                return false;
            }
        }
        return true;
    }

    /** Vérifie la composition du jury selon les 3 cas métier. */
    public function validateCompositionJury(int $idEtud, array $encadrant, array $juryIds): array
    {
        $errors   = [];
        $etudiant = (new Etudiant())->getById($idEtud);
        if (!$etudiant) {
            return ['Étudiant introuvable (id=' . $idEtud . ')'];
        }

        $langueAnglais    = $this->estLangueAnglais($etudiant['langue_pfe']);
        $encadrantAnglais = trim((string) $encadrant['specialite']) === SPECIALITE_ANGLAIS;

        $membres = [];
        $enc = $this->profModel->getById((int) $encadrant['id_prof']);
        if ($enc) {
            $membres[] = $enc;
        }
        foreach ($juryIds as $id) {
            $p = $this->profModel->getById((int) $id);
            if ($p) {
                $membres[] = $p;
            }
        }

        $nbInfo = 0;
        foreach ($membres as $m) {
            if (trim((string) $m['specialite']) === SPECIALITE_INFORMATIQUE) {
                $nbInfo++;
            }
        }

        if ($langueAnglais && !$encadrantAnglais) {
            $idsAnglais = array_column($this->profModel->getProfAnglais(), 'id_prof');
            if (empty(array_intersect($juryIds, $idsAnglais))) {
                $errors[] = 'CAS 1 : un professeur d\'anglais est obligatoire dans le jury.';
            }
            if ($nbInfo < MIN_INFORMATICIENS_JURY) {
                $errors[] = 'CAS 1 : minimum ' . MIN_INFORMATICIENS_JURY . ' informaticiens requis (encadrant + jury).';
            }
        } elseif ($langueAnglais && $encadrantAnglais) {
            if ($nbInfo < MIN_INFORMATICIENS_JURY) {
                $errors[] = 'CAS 2 : minimum ' . MIN_INFORMATICIENS_JURY . ' informaticiens requis (encadrant + jury).';
            }
        } else {
            if ($nbInfo < MIN_INFORMATICIENS_JURY) {
                $errors[] = 'CAS 3 : minimum ' . MIN_INFORMATICIENS_JURY . ' informaticiens requis (encadrant + jury).';
            }
        }

        return $errors;
    }

    /**
     * Vérifie que la répartition des étudiants entre les profs est équitable.
     * La règle est : chaque prof doit avoir floor(N/P) ou ceil(N/P) étudiants
     * (N = nb total étudiants, P = nb profs). Tout écart est signalé.
     */
    public function repartitionEquitable(): array
    {
        $profsData = $this->profModel->getWithNbEtudiants();
        if (empty($profsData)) {
            return [];
        }

        $nbTotal = array_sum(array_column($profsData, 'nb_etudiants'));
        $nbProfs = count($profsData);

        // Quotas théoriques : floor ou ceil selon la division
        $base  = intdiv($nbTotal, $nbProfs);
        $reste = $nbTotal % $nbProfs;
        // Les "reste" premiers profs (triés par charge croissante) auraient base+1

        $hors = [];
        foreach ($profsData as $p) {
            $nb = (int) $p['nb_etudiants'];
            // Acceptable si nb vaut base ou base+1 (quand il y a un reste)
            $acceptable = ($nb === $base) || ($reste > 0 && $nb === $base + 1);
            if (!$acceptable) {
                $attendu = $reste > 0 ? "$base ou " . ($base + 1) : (string) $base;
                $hors[]  = [
                    'id_prof'      => $p['id_prof'],
                    'nom'          => $p['nom_prof'] . ' ' . $p['prenom_prof'],
                    'nb_etudiants' => $nb,
                    'ecart'        => abs($nb - $base),
                    'type'         => $nb > $base + 1 ? 'surcharge' : 'sous-charge',
                    'attendu'      => $attendu,
                ];
            }
        }

        return $hors;
    }

    private function estLangueAnglais(?string $langue): bool
    {
        $l = mb_strtolower(trim((string) $langue));
        return $l === 'anglais' || $l === 'english';
    }
}
