<?php
declare(strict_types=1);

class PlanningCtrl
{
    private const SESSION_CONFIG = 'planning_config';

    public function index(): void
    {
        SessionStore::init();
        $erreur = null;
        $messages = [];
        $soutenances = SessionStore::getSoutenances();
        $sallesDisponibles = SessionStore::getSalles();

        $dateSessionDefaut = date('Y-m-d');
        $dateFinSessionDefaut = '';
        $heuresDebutDefaut = '';
        $sallesSelectionnees = null;
        $analysis = null;
        $analyseEffectuee = false;
        $peutReinitialiser = !empty($soutenances) || !empty(SessionStore::getCreneaux());

        require_once __DIR__ . '/../../views/planning.view.php';
    }

    public function analyser(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_PATH . '/planning');
            exit;
        }

        SessionStore::init();
        $erreur = null;
        $messages = [];
        $soutenances = SessionStore::getSoutenances();
        $analysis = null;
        $analyseEffectuee = false;

        $params = $this->parseParams($_POST);
        $sallesDisponibles = SessionStore::getSalles();
        $sallesAffichees = $this->parseSalles($_POST['salles'] ?? []);

        if ($params['erreur'] !== null) {
            $erreur = $params['erreur'];
        } else {
            $analysis = (new PlanningAnalysisSvc())->analyser(
                $params['date_debut'],
                $params['date_fin'],
                $params['heures'],
                $params['salles']
            );
            $analyseEffectuee = true;

            $_SESSION[self::SESSION_CONFIG] = [
                'date_debut' => $params['date_debut'],
                'date_fin'   => $params['date_fin'],
                'heures'     => $params['heures'],
                'salles'     => $params['salles'],
                'analysis'   => $analysis,
            ];
        }

        $dateSessionDefaut = $params['date_debut'] ?? date('Y-m-d');
        $dateFinSessionDefaut = $params['date_fin'] ?? '';
        $heuresDebutDefaut = isset($params['heures']) ? implode(', ', $params['heures']) : trim((string) ($_POST['plages_horaires'] ?? ''));
        $sallesSelectionnees = $params['erreur'] !== null ? $sallesAffichees : ($params['salles'] ?? []);
        $peutReinitialiser = !empty($soutenances) || !empty(SessionStore::getCreneaux());

        require_once __DIR__ . '/../../views/planning.view.php';
    }

    public function generer(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_PATH . '/planning');
            exit;
        }

        SessionStore::init();
        $erreur = null;
        $messages = [];
        $soutenances = SessionStore::getSoutenances();
        $analysis = null;
        $analyseEffectuee = false;

        $config = $_SESSION[self::SESSION_CONFIG] ?? null;
        if ($config === null) {
            $erreur = 'Veuillez d\'abord analyser la faisabilité avant de générer le planning.';
            $dateSessionDefaut = date('Y-m-d');
            $dateFinSessionDefaut = '';
            $heuresDebutDefaut = '';
            $sallesDisponibles = SessionStore::getSalles();
            $sallesSelectionnees = null;
        } else {
            try {
                $res = (new PlanningSvc())->planifier(
                    $config['date_debut'],
                    $config['date_fin'],
                    $config['heures'],
                    $config['salles']
                );
                if ($res['planifies'] > 0) {
                    $messages[] = ['type' => 'success', 'text' => $res['planifies'] . ' soutenance(s) planifiée(s).'];
                }
                foreach ($res['avertissements'] as $m) {
                    $messages[] = ['type' => 'warning', 'text' => $m];
                }
                foreach ($res['echecs'] as $m) {
                    $messages[] = ['type' => 'danger', 'text' => $m];
                }
                $soutenances = SessionStore::getSoutenances();
            } catch (\Throwable $e) {
                $erreur = $e->getMessage();
            }

            $analysis = $config['analysis'];
            $analyseEffectuee = true;
            $dateSessionDefaut = $config['date_debut'];
            $dateFinSessionDefaut = $config['date_fin'];
            $heuresDebutDefaut = implode(', ', $config['heures']);
            $sallesDisponibles = SessionStore::getSalles();
            $sallesSelectionnees = $config['salles'];
        }

        $peutReinitialiser = !empty($soutenances) || !empty(SessionStore::getCreneaux());

        require_once __DIR__ . '/../../views/planning.view.php';
    }

    public function reinitialiser(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_PATH . '/planning');
            exit;
        }

        SessionStore::init();
        SessionStore::resetPlanning();
        unset($_SESSION[self::SESSION_CONFIG]);

        $_SESSION['flash'] = [
            'type'    => 'success',
            'message' => 'Planning réinitialisé : toutes les soutenances et créneaux ont été supprimés.',
        ];
        header('Location: ' . BASE_PATH . '/planning');
        exit;
    }

    public function export(): void
    {
        SessionStore::init();
        $filepath = (new PdfPlanningSvc())->generer();
        if (!file_exists($filepath)) {
            http_response_code(404);
            echo 'Fichier introuvable.';
            return;
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
        header('Content-Length: ' . (string) filesize($filepath));
        readfile($filepath);
        exit;
    }

    /**
     * @param array<string,mixed> $post
     * @return array{
     *   erreur:?string,
     *   date_debut:?string,
     *   date_fin:?string,
     *   heures:array<int,string>,
     *   salles:array<int,int>
     * }
     */
    private function parseParams(array $post): array
    {
        $dateDebut = $this->parseDate(trim((string) ($post['date_debut_session'] ?? '')));
        $dateFin   = $this->parseDate(trim((string) ($post['date_fin_session'] ?? '')));
        $heures    = $this->parseHeuresDebut((string) ($post['plages_horaires'] ?? ''));
        $salles    = $this->parseSalles($post['salles'] ?? []);
        $sallesDisponibles = SessionStore::getSalles();

        if ($salles === []) {
            $salles = $sallesDisponibles;
        }

        if (!$dateDebut) {
            return ['erreur' => 'Date de début invalide.', 'date_debut' => null, 'date_fin' => null, 'heures' => [], 'salles' => []];
        }
        if (!$dateFin) {
            return ['erreur' => 'Date de fin invalide.', 'date_debut' => $dateDebut, 'date_fin' => null, 'heures' => [], 'salles' => []];
        }
        if ($dateFin < $dateDebut) {
            return ['erreur' => 'La date de fin doit être >= à la date de début.', 'date_debut' => $dateDebut, 'date_fin' => $dateFin, 'heures' => [], 'salles' => []];
        }
        if ($heures === []) {
            return ['erreur' => 'Indiquez au moins une heure de début (ex: 09:00, 10:00).', 'date_debut' => $dateDebut, 'date_fin' => $dateFin, 'heures' => [], 'salles' => $salles];
        }
        if ($salles === []) {
            return ['erreur' => 'Aucune salle disponible. Importez des salles avant de planifier.', 'date_debut' => $dateDebut, 'date_fin' => $dateFin, 'heures' => $heures, 'salles' => []];
        }

        return [
            'erreur'     => null,
            'date_debut' => $dateDebut,
            'date_fin'   => $dateFin,
            'heures'     => $heures,
            'salles'     => $salles,
        ];
    }

    private function parseDate(string $s): ?string
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            return null;
        }
        $ts = strtotime($s . ' 12:00:00');
        return ($ts && date('Y-m-d', $ts) === $s) ? $s : null;
    }

    /**
     * @return array<int,string>
     */
    private function parseHeuresDebut(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }
        $parts = preg_split('/[,\n;]+/', $raw) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '') {
                continue;
            }
            if (!preg_match('/^\d{2}:\d{2}$/', $p)) {
                continue;
            }
            $h = (int) substr($p, 0, 2);
            $m = (int) substr($p, 3, 2);
            if ($h < 0 || $h > 23 || $m < 0 || $m > 59) {
                continue;
            }
            $out[] = sprintf('%02d:%02d', $h, $m);
        }
        $out = array_values(array_unique($out));
        sort($out);
        return $out;
    }

    /**
     * @param mixed $raw
     * @return array<int,int>
     */
    private function parseSalles($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $v) {
            if (is_string($v) && preg_match('/^\d+$/', $v)) {
                $out[] = (int) $v;
            } elseif (is_int($v) && $v > 0) {
                $out[] = $v;
            }
        }
        $out = array_values(array_unique(array_filter($out, fn($n) => $n > 0)));
        sort($out);
        return $out;
    }
}
