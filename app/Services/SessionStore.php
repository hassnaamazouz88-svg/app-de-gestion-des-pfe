<?php
declare(strict_types=1);

/**
 * Couche de persistance en mémoire via $_SESSION.
 * Remplace complètement PDO / MySQL.
 *
 * Structure de $_SESSION['gestion_pfe'] :
 *   etudiants  : array<int, array{id_etud, nom_etud, prenom_etud, email, filiere, sujet_pfe, langue_pfe, id_prof}>
 *   professeurs: array<int, array{id_prof, nom_prof, prenom_prof, specialite}>
 *   salles     : array<int, int>   (liste de numéros de salle)
 *   soutenances: array<int, array{id_stnc, id_etud, id_cren, num_salle}>
 *   creneaux   : array<int, array{id_cren, date_cren, heure_debut, heure_fin}>
 *   jury       : array<int, array{id_stnc, id_prof, role_jury}>
 *   _seq       : array{etud, prof, stnc, cren, jury}   (auto-incrément)
 */
class SessionStore
{
    private static string $KEY = 'gestion_pfe';

    // ── Initialisation ───────────────────────────────────────────

    public static function init(): void
    {
        if (!isset($_SESSION[self::$KEY])) {
            $_SESSION[self::$KEY] = [
                'etudiants'   => [],
                'professeurs' => [],
                'salles'      => [],
                'soutenances' => [],
                'creneaux'    => [],
                'jury'        => [],
                '_seq'        => ['etud' => 1, 'prof' => 1, 'stnc' => 1, 'cren' => 1, 'jury' => 1],
            ];
        }
    }

    private static function &store(): array
    {
        self::init();
        return $_SESSION[self::$KEY];
    }

    private static function nextId(string $key): int
    {
        $id = $_SESSION[self::$KEY]['_seq'][$key];
        $_SESSION[self::$KEY]['_seq'][$key]++;
        return $id;
    }

    // ── Reset complet ────────────────────────────────────────────

    public static function reset(): void
    {
        unset($_SESSION[self::$KEY]);
        self::init();
    }

    public static function resetPlanning(): void
    {
        self::init();
        $_SESSION[self::$KEY]['soutenances'] = [];
        $_SESSION[self::$KEY]['creneaux']    = [];
        $_SESSION[self::$KEY]['jury']        = [];
        $_SESSION[self::$KEY]['_seq']['stnc'] = 1;
        $_SESSION[self::$KEY]['_seq']['cren'] = 1;
        $_SESSION[self::$KEY]['_seq']['jury'] = 1;
    }

    public static function resetAffectation(): void
    {
        self::init();
        foreach ($_SESSION[self::$KEY]['etudiants'] as &$e) {
            $e['id_prof'] = null;
        }
        unset($e);
        self::resetPlanning();
    }

    // ── Compteurs ────────────────────────────────────────────────

    public static function compter(): array
    {
        self::init();
        $s = &self::store();
        return [
            'etudiants' => count($s['etudiants']),
            'profs'     => count($s['professeurs']),
            'salles'    => count($s['salles']),
        ];
    }

    // ════════════════════════════════════════════════════════════
    //  ÉTUDIANTS
    // ════════════════════════════════════════════════════════════

    public static function ajouterEtudiant(array $data): int
    {
        self::init();
        $id = self::nextId('etud');
        $_SESSION[self::$KEY]['etudiants'][$id] = [
            'id_etud'     => $id,
            'nom_etud'    => $data['nom_etud'],
            'prenom_etud' => $data['prenom_etud'],
            'email'       => $data['email'] ?? '',
            'filiere'     => $data['filiere'],
            'sujet_pfe'   => $data['sujet_pfe'] ?? null,
            'langue_pfe'  => $data['langue_pfe'] ?? null,
            'id_prof'     => null,
        ];
        return $id;
    }

    public static function getEtudiants(): array
    {
        self::init();
        return array_values($_SESSION[self::$KEY]['etudiants']);
    }

    public static function getEtudiantById(int $id): ?array
    {
        self::init();
        return $_SESSION[self::$KEY]['etudiants'][$id] ?? null;
    }

    public static function getEtudiantByEmail(string $email): ?array
    {
        self::init();
        $email = mb_strtolower(trim($email));
        foreach ($_SESSION[self::$KEY]['etudiants'] as $e) {
            if (mb_strtolower(trim($e['email'])) === $email) {
                return $e;
            }
        }
        return null;
    }

    public static function updateEtudiant(int $id, array $data): void
    {
        self::init();
        if (isset($_SESSION[self::$KEY]['etudiants'][$id])) {
            $_SESSION[self::$KEY]['etudiants'][$id] = array_merge(
                $_SESSION[self::$KEY]['etudiants'][$id],
                $data
            );
        }
    }

    public static function setEncadrant(int $idEtud, int $idProf): void
    {
        self::init();
        if (isset($_SESSION[self::$KEY]['etudiants'][$idEtud])) {
            $_SESSION[self::$KEY]['etudiants'][$idEtud]['id_prof'] = $idProf;
        }
    }

    public static function getEtudiantsSansEncadrant(): array
    {
        self::init();
        return array_values(array_filter(
            $_SESSION[self::$KEY]['etudiants'],
            fn($e) => $e['id_prof'] === null
        ));
    }

    public static function getEtudiantsSansSoutenance(): array
    {
        self::init();
        $avecSoutenance = array_unique(
            array_column($_SESSION[self::$KEY]['soutenances'], 'id_etud')
        );
        return array_values(array_filter(
            $_SESSION[self::$KEY]['etudiants'],
            fn($e) => !in_array($e['id_etud'], $avecSoutenance, true)
        ));
    }

    public static function supprimerEtudiants(): int
    {
        self::init();
        $nb = count($_SESSION[self::$KEY]['etudiants']);
        $_SESSION[self::$KEY]['etudiants'] = [];
        $_SESSION[self::$KEY]['_seq']['etud'] = 1;
        self::resetPlanning();
        return $nb;
    }

    // ════════════════════════════════════════════════════════════
    //  PROFESSEURS
    // ════════════════════════════════════════════════════════════

    public static function ajouterProfesseur(array $data): int
    {
        self::init();
        $id = self::nextId('prof');
        $_SESSION[self::$KEY]['professeurs'][$id] = [
            'id_prof'     => $id,
            'nom_prof'    => $data['nom_prof'],
            'prenom_prof' => $data['prenom_prof'],
            'specialite'  => $data['specialite'],
        ];
        return $id;
    }

    public static function getProfesseurs(): array
    {
        self::init();
        return array_values($_SESSION[self::$KEY]['professeurs']);
    }

    public static function getProfesseurById(int $id): ?array
    {
        self::init();
        return $_SESSION[self::$KEY]['professeurs'][$id] ?? null;
    }

    public static function getProfesseursBySpecialite(string $specialite): array
    {
        self::init();
        return array_values(array_filter(
            $_SESSION[self::$KEY]['professeurs'],
            fn($p) => mb_strtolower(trim($p['specialite'])) === mb_strtolower(trim($specialite))
        ));
    }

    public static function getProfesseursAvecNbEtudiants(): array
    {
        self::init();
        $profs = self::getProfesseurs();
        $etuds = self::getEtudiants();
        foreach ($profs as &$p) {
            $p['nb_etudiants'] = count(array_filter($etuds, fn($e) => $e['id_prof'] === $p['id_prof']));
        }
        unset($p);
        return $profs;
    }

    public static function supprimerProfesseurs(): int
    {
        self::init();
        $nb = count($_SESSION[self::$KEY]['professeurs']);
        $_SESSION[self::$KEY]['professeurs'] = [];
        $_SESSION[self::$KEY]['_seq']['prof'] = 1;
        self::resetAffectation();
        return $nb;
    }

    // ════════════════════════════════════════════════════════════
    //  SALLES
    // ════════════════════════════════════════════════════════════

    public static function ajouterSalle(int $num): void
    {
        self::init();
        if (!in_array($num, $_SESSION[self::$KEY]['salles'], true)) {
            $_SESSION[self::$KEY]['salles'][] = $num;
            sort($_SESSION[self::$KEY]['salles']);
        }
    }

    public static function salleExiste(int $num): bool
    {
        self::init();
        return in_array($num, $_SESSION[self::$KEY]['salles'], true);
    }

    public static function getSalles(): array
    {
        self::init();
        return $_SESSION[self::$KEY]['salles'];
    }

    public static function getSallesDisponibles(int $idCren): array
    {
        self::init();
        $occupees = array_column(
            array_filter($_SESSION[self::$KEY]['soutenances'], fn($s) => $s['id_cren'] === $idCren),
            'num_salle'
        );
        return array_values(array_filter(
            $_SESSION[self::$KEY]['salles'],
            fn($n) => !in_array($n, $occupees, true)
        ));
    }

    /**
     * @param array<int,int> $sallesAutorisees
     * @return array<int,int>
     */
    public static function getSallesDisponiblesDans(int $idCren, array $sallesAutorisees): array
    {
        self::init();
        $occupees = array_column(
            array_filter($_SESSION[self::$KEY]['soutenances'], fn($s) => $s['id_cren'] === $idCren),
            'num_salle'
        );
        return array_values(array_filter(
            $sallesAutorisees,
            fn($n) => !in_array($n, $occupees, true)
        ));
    }

    public static function supprimerSalles(): int
    {
        self::init();
        $nb = count($_SESSION[self::$KEY]['salles']);
        $_SESSION[self::$KEY]['salles'] = [];
        self::resetPlanning();
        return $nb;
    }

    // ════════════════════════════════════════════════════════════
    //  CRÉNEAUX
    // ════════════════════════════════════════════════════════════

    public static function ajouterCreneau(array $data): int
    {
        self::init();
        $id = self::nextId('cren');
        $_SESSION[self::$KEY]['creneaux'][$id] = [
            'id_cren'     => $id,
            'date_cren'   => $data['date_cren'],
            'heure_debut' => $data['heure_debut'],
            'heure_fin'   => $data['heure_fin'],
        ];
        return $id;
    }

    public static function getCreneaux(): array
    {
        self::init();
        $creneaux = array_values($_SESSION[self::$KEY]['creneaux']);
        usort($creneaux, fn($a, $b) => strcmp($a['date_cren'].$a['heure_debut'], $b['date_cren'].$b['heure_debut']));
        return $creneaux;
    }

    public static function getCreneauById(int $id): ?array
    {
        self::init();
        return $_SESSION[self::$KEY]['creneaux'][$id] ?? null;
    }

    public static function getDateMinCreneaux(): ?string
    {
        self::init();
        if (empty($_SESSION[self::$KEY]['creneaux'])) return null;
        return min(array_column($_SESSION[self::$KEY]['creneaux'], 'date_cren'));
    }

    public static function getDateMaxCreneaux(): ?string
    {
        self::init();
        if (empty($_SESSION[self::$KEY]['creneaux'])) return null;
        return max(array_column($_SESSION[self::$KEY]['creneaux'], 'date_cren'));
    }

    /**
     * Génère des créneaux sur une période, à partir d'une liste d'heures "HH:MM".
     *
     * @param array<int,string> $heuresDebut
     */
    public static function genererCreneauxPlages(string $dateDebut, string $dateFin, array $heuresDebut): void
    {
        self::init();
        $t0 = strtotime($dateDebut . ' 12:00:00');
        $t1 = strtotime($dateFin . ' 12:00:00');
        if (!$t0 || !$t1 || $t1 < $t0) return;

        $duree = DUREE_SOUTENANCE;
        $jours = (int) round(($t1 - $t0) / 86400) + 1;

        for ($j = 0; $j < $jours; $j++) {
            $date = date('Y-m-d', strtotime("$dateDebut +$j days"));
            foreach ($heuresDebut as $heureDebut) {
                $heureDebut = trim((string)$heureDebut);
                if ($heureDebut === '') continue;
                $ts = strtotime("$date $heureDebut");
                if (!$ts) continue;
                self::ajouterCreneau([
                    'date_cren'   => $date,
                    'heure_debut' => date('H:i:s', $ts),
                    'heure_fin'   => date('H:i:s', $ts + $duree * 60),
                ]);
            }
        }
    }

    // ════════════════════════════════════════════════════════════
    //  SOUTENANCES
    // ════════════════════════════════════════════════════════════

    public static function ajouterSoutenance(array $data): int
    {
        self::init();
        $id = self::nextId('stnc');
        $_SESSION[self::$KEY]['soutenances'][$id] = [
            'id_stnc'   => $id,
            'id_etud'   => $data['id_etud'],
            'id_cren'   => $data['id_cren'],
            'num_salle' => $data['num_salle'],
        ];
        return $id;
    }

    public static function getSoutenances(): array
    {
        self::init();
        // Enrichir avec les données liées
        $result = [];
        foreach ($_SESSION[self::$KEY]['soutenances'] as $s) {
            $etud = self::getEtudiantById($s['id_etud']);
            $cren = self::getCreneauById($s['id_cren']);
            if (!$etud || !$cren) continue;

            $jury     = self::getJuryParSoutenance($s['id_stnc']);
            $encadrant = null;
            $juryMembres = [];
            foreach ($jury as $j) {
                $prof = self::getProfesseurById($j['id_prof']);
                if (!$prof) continue;
                $membre = array_merge($j, ['nom_prof' => $prof['nom_prof'], 'prenom_prof' => $prof['prenom_prof'], 'specialite' => $prof['specialite']]);
                if ($j['role_jury'] === 'encadrant') {
                    $encadrant = $membre;
                } else {
                    $juryMembres[] = $membre;
                }
            }

            $result[] = array_merge($s, [
                'nom_etud'         => $etud['nom_etud'],
                'prenom_etud'      => $etud['prenom_etud'],
                'filiere'          => $etud['filiere'],
                'sujet_pfe'        => $etud['sujet_pfe'],
                'langue_pfe'       => $etud['langue_pfe'],
                'date_cren'        => $cren['date_cren'],
                'heure_debut'      => $cren['heure_debut'],
                'heure_fin'        => $cren['heure_fin'],
                'nom_encadrant'    => $encadrant['nom_prof'] ?? '',
                'prenom_encadrant' => $encadrant['prenom_prof'] ?? '',
                'nom_jury1'        => $juryMembres[0]['nom_prof'] ?? '',
                'prenom_jury1'     => $juryMembres[0]['prenom_prof'] ?? '',
                'nom_jury2'        => $juryMembres[1]['nom_prof'] ?? '',
                'prenom_jury2'     => $juryMembres[1]['prenom_prof'] ?? '',
            ]);
        }
        usort($result, fn($a, $b) => strcmp($a['date_cren'].$a['heure_debut'], $b['date_cren'].$b['heure_debut']));
        return $result;
    }

    public static function getSoutenanceById(int $id): ?array
    {
        self::init();
        $soutenances = self::getSoutenances();
        foreach ($soutenances as $s) {
            if ($s['id_stnc'] === $id) return $s;
        }
        return null;
    }

    public static function getSoutenancesParProf(int $idProf): array
    {
        self::init();
        $juryProf = array_filter($_SESSION[self::$KEY]['jury'], fn($j) => $j['id_prof'] === $idProf);
        $idsStnc  = array_unique(array_column($juryProf, 'id_stnc'));
        return array_values(array_filter(self::getSoutenances(), fn($s) => in_array($s['id_stnc'], $idsStnc, true)));
    }

    public static function getSoutenancesParCreneau(int $idCren): array
    {
        self::init();
        return array_values(array_filter(
            $_SESSION[self::$KEY]['soutenances'],
            fn($s) => $s['id_cren'] === $idCren
        ));
    }

    // ════════════════════════════════════════════════════════════
    //  JURY (Participer)
    // ════════════════════════════════════════════════════════════

    public static function ajouterJury(int $idStnc, int $idProf, string $role): void
    {
        self::init();
        $id = self::nextId('jury');
        $_SESSION[self::$KEY]['jury'][$id] = [
            'id'       => $id,
            'id_stnc'  => $idStnc,
            'id_prof'  => $idProf,
            'role_jury'=> $role,
        ];
    }

    public static function getJuryParSoutenance(int $idStnc): array
    {
        self::init();
        return array_values(array_filter(
            $_SESSION[self::$KEY]['jury'],
            fn($j) => $j['id_stnc'] === $idStnc
        ));
    }

    public static function isProfDisponible(int $idProf, int $idCren): bool
    {
        self::init();
        foreach ($_SESSION[self::$KEY]['jury'] as $j) {
            if ($j['id_prof'] !== $idProf) continue;
            $stnc = $_SESSION[self::$KEY]['soutenances'][$j['id_stnc']] ?? null;
            if ($stnc && $stnc['id_cren'] === $idCren) return false;
        }
        return true;
    }

    public static function hasRepos(int $idProf, int $idCren): bool
    {
        $candidat = self::getCreneauById($idCren);
        if (!$candidat) return false;

        $debutC = strtotime($candidat['date_cren'] . ' ' . $candidat['heure_debut']);
        $finC   = strtotime($candidat['date_cren'] . ' ' . $candidat['heure_fin']);
        $reposMin = REPOS_MIN_PROF * 60;

        foreach (self::getSoutenancesParProf($idProf) as $s) {
            $debutS = strtotime($s['date_cren'] . ' ' . $s['heure_debut']);
            $finS   = strtotime($s['date_cren'] . ' ' . $s['heure_fin']);
            if ($debutC >= $finS && ($debutC - $finS) < $reposMin) return false;
            if ($finC <= $debutS && ($debutS - $finC) < $reposMin) return false;
        }
        return true;
    }
}
