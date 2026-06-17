<?php
declare(strict_types=1);

class ExcelImportSvc
{
    private const ETUDIANT_ALIASES = [
        'nom_etud'    => ['nom_etud','nom','nom_etudiant','nom_de_l_etudiant','name','lastname','last_name'],
        'prenom_etud' => ['prenom_etud','prenom','prenom_etudiant','firstname','first_name','prenom_de_l_etudiant'],
        'email'       => ['email','mail','e_mail','courriel','adresse_email','adresse_mail'],
        'filiere'     => ['filiere','filiere_etud','filiere_etudiant','department','departement','dept'],
        'sujet_pfe'   => ['sujet_pfe','sujet','sujet_de_pfe','theme','titre','sujet_du_pfe'],
    ];

    private const PROF_ALIASES = [
        'nom_prof'    => ['nom_prof','nom','nom_professeur','name','lastname','last_name'],
        'prenom_prof' => ['prenom_prof','prenom','prenom_professeur','firstname','first_name'],
        'specialite'  => ['specialite','specialite_prof','specialty','domaine','discipline'],
    ];

    private const SALLE_ALIASES = [
        'num_salle' => ['num_salle','numero_salle','salle','numero','num','room','n_salle'],
    ];

    private const SHEET_ETUDIANTS = ['etudiants','etudiant','students','student','liste_etudiants'];
    private const SHEET_PROFS     = ['professeurs','professeur','profs','prof','enseignants','teachers'];
    private const SHEET_SALLES    = ['salles','salle','rooms','room','locaux'];

    /**
     * Importe le fichier Excel multi-feuilles et stocke tout en session via SessionStore.
     */
    public function importFichierComplet(array $file): array
    {
        $global = ['etudiants' => [], 'professeurs' => [], 'salles' => [], 'errors' => []];

        if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            $global['errors'][] = 'Erreur lors de l\'upload du fichier.';
            return $global;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext === 'csv') {
            $global['errors'][] = 'Veuillez fournir un fichier .xlsx ou .xls avec 3 feuilles.';
            return $global;
        }

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
        } catch (\Throwable $e) {
            $global['errors'][] = 'Impossible de lire le fichier : ' . $e->getMessage();
            return $global;
        }

        $sheets = $spreadsheet->getAllSheets();
        if (count($sheets) < 3) {
            $global['errors'][] = 'Le fichier doit contenir au moins 3 feuilles : Étudiants, Professeurs, Salles.';
            return $global;
        }

        $sheetEtud  = $this->trouverFeuille($sheets, self::SHEET_ETUDIANTS)  ?? $sheets[0];
        $sheetProfs = $this->trouverFeuille($sheets, self::SHEET_PROFS)       ?? $sheets[1];
        $sheetSalle = $this->trouverFeuille($sheets, self::SHEET_SALLES)      ?? $sheets[2];

        // Réinitialiser les données précédentes avant un nouvel import
        SessionStore::reset();
        unset($_SESSION['planning_config']);

        $global['etudiants']   = $this->traiterEtudiants($sheetEtud);
        $global['professeurs'] = $this->traiterProfs($sheetProfs);
        $global['salles']      = $this->traiterSalles($sheetSalle);

        return $global;
    }

    // ── Traitement feuille Étudiants ─────────────────────────────

    private function traiterEtudiants(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array
    {
        $result = ['imported' => 0, 'warnings' => [], 'errors' => []];
        $seenEmails = [];
        $rows = $this->feuilleToRows($sheet);

        if ($err = $this->validerColonnes($rows, self::ETUDIANT_ALIASES, ['nom_etud','prenom_etud','filiere'],
            'Colonnes attendues : nom_etud, prenom_etud, filiere.')) {
            $result['errors'][] = '[Feuille Étudiants] ' . $err;
            return $result;
        }

        foreach ($rows as $i => $row) {
            $ligne  = $i + 2;
            $nom    = $this->champ($row, self::ETUDIANT_ALIASES['nom_etud']);
            $prenom = $this->champ($row, self::ETUDIANT_ALIASES['prenom_etud']);
            $filiere= $this->champ($row, self::ETUDIANT_ALIASES['filiere']);
            $email  = $this->champ($row, self::ETUDIANT_ALIASES['email']);
            $sujet  = $this->champ($row, self::ETUDIANT_ALIASES['sujet_pfe']);

            if ($nom === '' || $prenom === '' || $filiere === '') {
                $result['errors'][] = "Ligne $ligne : nom, prénom ou filière manquant.";
                continue;
            }

            $email = mb_strtolower($email !== '' ? $email : $this->buildEmail($nom, $prenom));

            if (isset($seenEmails[$email])) {
                $result['warnings'][] = "Ligne $ligne : email en double ($email), ignorée.";
                continue;
            }
            $seenEmails[$email] = true;

            Etudiant::create([
                'nom_etud'    => $nom,
                'prenom_etud' => $prenom,
                'email'       => $email,
                'filiere'     => $filiere,
                'sujet_pfe'   => $sujet ?: null,
                'langue_pfe'  => null,
            ]);
            $result['imported']++;
        }
        return $result;
    }

    // ── Traitement feuille Professeurs ───────────────────────────

    private function traiterProfs(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array
    {
        $result = ['imported' => 0, 'errors' => []];
        $rows = $this->feuilleToRows($sheet);

        if ($err = $this->validerColonnes($rows, self::PROF_ALIASES, ['nom_prof','prenom_prof','specialite'],
            'Colonnes attendues : nom_prof, prenom_prof, specialite.')) {
            $result['errors'][] = '[Feuille Professeurs] ' . $err;
            return $result;
        }

        foreach ($rows as $i => $row) {
            $ligne      = $i + 2;
            $nom        = $this->champ($row, self::PROF_ALIASES['nom_prof']);
            $prenom     = $this->champ($row, self::PROF_ALIASES['prenom_prof']);
            $specialite = $this->champ($row, self::PROF_ALIASES['specialite']);

            if ($nom === '' || $prenom === '' || $specialite === '') {
                $result['errors'][] = "Ligne $ligne : nom, prénom ou spécialité manquant.";
                continue;
            }

            Professeur::create([
                'nom_prof'    => $nom,
                'prenom_prof' => $prenom,
                'specialite'  => $specialite,
            ]);
            $result['imported']++;
        }
        return $result;
    }

    // ── Traitement feuille Salles ────────────────────────────────

    private function traiterSalles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array
    {
        $result = ['imported' => 0, 'skipped' => 0, 'warnings' => [], 'errors' => []];
        $seen = [];
        $rows = $this->feuilleToRows($sheet);

        if ($err = $this->validerColonnes($rows, self::SALLE_ALIASES, ['num_salle'],
            'Colonne attendue : num_salle.')) {
            $result['errors'][] = '[Feuille Salles] ' . $err;
            return $result;
        }

        foreach ($rows as $i => $row) {
            $ligne = $i + 2;
            $raw   = $this->champ($row, self::SALLE_ALIASES['num_salle']);
            $num   = $this->parseNumSalle($raw);

            if ($num === null) {
                if ($raw !== '') $result['errors'][] = "Ligne $ligne : numéro invalide ($raw).";
                continue;
            }
            if (isset($seen[$num])) {
                $result['warnings'][] = "Ligne $ligne : salle $num en doublon, ignorée.";
                $result['skipped']++;
                continue;
            }
            $seen[$num] = true;

            if (Salle::exists($num)) {
                $result['skipped']++;
                continue;
            }
            Salle::create($num);
            $result['imported']++;
        }
        return $result;
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function trouverFeuille(array $sheets, array $patterns): ?\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
    {
        foreach ($sheets as $sheet) {
            $nom = $this->normalizeHeader($sheet->getTitle());
            foreach ($patterns as $p) {
                if ($nom === $p || str_contains($nom, $p)) return $sheet;
            }
        }
        return null;
    }

    private function feuilleToRows(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array
    {
        $data = $sheet->toArray(null, true, true, false);
        if (empty($data)) return [];
        $hi      = $this->findHeaderRow($data);
        $headers = $this->normalizeHeaderRow($data[$hi]);
        $rows    = [];
        for ($i = $hi + 1, $n = count($data); $i < $n; $i++) {
            $row = $data[$i];
            if (!is_array($row) || !$this->rowHasData($row)) continue;
            $rows[] = $this->combineRow($headers, $row);
        }
        return $rows;
    }

    private function findHeaderRow(array $data): int
    {
        $known = array_merge(...array_values(self::ETUDIANT_ALIASES), ...array_values(self::PROF_ALIASES), ...array_values(self::SALLE_ALIASES));
        foreach ($data as $idx => $row) {
            if (!is_array($row)) continue;
            $norm = array_map(fn($c) => $this->normalizeHeader((string)$c), $row);
            $matches = count(array_filter($norm, fn($c) => $c !== '' && in_array($c, $known, true)));
            if ($matches >= 1) return $idx;
        }
        return 0;
    }

    private function normalizeHeaderRow(array $row): array
    {
        return array_map(fn($c) => $this->normalizeHeader((string)$c), $row);
    }

    private function normalizeHeader(string $h): string
    {
        $h = preg_replace('/^\xEF\xBB\xBF/', '', trim($h)) ?? trim($h);
        $h = mb_strtolower($h, 'UTF-8');
        $h = strtr($h, [
            'à'=>'a','â'=>'a','ä'=>'a','á'=>'a',
            'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
            'î'=>'i','ï'=>'i','ì'=>'i','í'=>'i',
            'ô'=>'o','ö'=>'o','ò'=>'o','ó'=>'o',
            'ù'=>'u','û'=>'u','ü'=>'u','ú'=>'u',
            'ç'=>'c','n'=>'n',
            "\xe2\x80\x98"=>'',"\xe2\x80\x99"=>'', "'"=>'',
        ]);
        $h = preg_replace('/[^a-z0-9]+/', '_', $h) ?? $h;
        return trim($h, '_');
    }

    private function combineRow(array $headers, array $row): array
    {
        $values = array_values($row);
        $count  = count($headers);
        if (count($values) > $count) $values = array_slice($values, 0, $count);
        while (count($values) < $count) $values[] = '';
        $c = array_combine($headers, $values);
        return is_array($c) ? $c : [];
    }

    private function rowHasData(array $row): bool
    {
        foreach ($row as $v) {
            if ($v !== null && trim((string)$v) !== '') return true;
        }
        return false;
    }

    private function champ(array $row, array $aliases): string
    {
        foreach ($aliases as $a) {
            if (array_key_exists($a, $row)) {
                $v = trim((string)$row[$a]);
                if ($v !== '') return $v;
            }
        }
        return '';
    }

    private function validerColonnes(array $rows, array $groups, array $required, string $hint = ''): ?string
    {
        if ($rows === []) return 'La feuille est vide.';
        $keys    = array_keys($rows[0]);
        $missing = [];
        foreach ($required as $field) {
            $found = false;
            foreach ($groups[$field] ?? [$field] as $alias) {
                if (in_array($alias, $keys, true)) { $found = true; break; }
            }
            if (!$found) $missing[] = $field;
        }
        if ($missing === []) return null;
        return 'Colonnes manquantes : ' . implode(', ', $missing) . '. Détectées : ' . implode(', ', $keys) . '. ' . $hint;
    }

    private function buildEmail(string $nom, string $prenom): string
    {
        return $this->normalizeHeader($nom) . '.' . $this->normalizeHeader($prenom) . '@univ.ma';
    }

    private function parseNumSalle(string $raw): ?int
    {
        $raw = trim($raw);
        if ($raw === '') return null;
        if (preg_match('/^-?\d+$/', $raw)) { $n = (int)$raw; return $n > 0 ? $n : null; }
        if (preg_match('/^-?\d+\.\d+$/', $raw)) {
            $f = (float)$raw;
            if (abs($f - round($f)) < 1e-9 && $f > 0) return (int)round($f);
        }
        return null;
    }
}
