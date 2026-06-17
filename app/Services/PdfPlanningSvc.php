<?php
declare(strict_types=1);

use Dompdf\Dompdf;
use Dompdf\Options;

class PdfPlanningSvc
{
    private string $outputDir;

    /** @var array<int,string> */
    private array $couleursProf = [];

    /** @var array<int,string> */
    private const PALETTE = [
        '#b6d7a8', '#d9d9d9', '#f9cb9c', '#c9daf8', '#ead1dc',
        '#ffe599', '#a4c2f4', '#d5a6bd', '#b4a7d6', '#ea9999',
        '#76a5af', '#f6b26b', '#8e7cc3', '#93c47d', '#e69138',
    ];

    public function __construct()
    {
        $this->outputDir = __DIR__ . '/../../public/outputs/pdf_planning/';
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
    }

    public function generer(): string
    {
        $soutenances = SessionStore::getSoutenances();
        usort($soutenances, function ($a, $b) {
            $cmp = strcmp($a['date_cren'] . $a['heure_debut'], $b['date_cren'] . $b['heure_debut']);
            return $cmp !== 0 ? $cmp : ($a['num_salle'] <=> $b['num_salle']);
        });

        $corps = $this->construireTableau($soutenances);
        $anneeUni = $this->anneeUniversitaire();

        $html = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  @page { margin: 12mm 10mm; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #000; margin: 0; }
  .entete { text-align: center; margin-bottom: 14px; line-height: 1.5; }
  .entete .l1 { font-size: 11px; font-weight: bold; }
  .entete .l2 { font-size: 10px; }
  .entete .l3 { font-size: 10px; font-weight: bold; margin-top: 4px; }
  .entete .l4 { font-size: 9px; margin-top: 2px; }
  table.planning { width: 100%; border-collapse: collapse; table-layout: fixed; }
  table.planning th {
    background: #000; color: #fff; font-weight: bold;
    padding: 5px 3px; font-size: 7.5px; text-align: center;
    border: 1px solid #000;
  }
  table.planning td {
    padding: 4px 3px; font-size: 7.5px; text-align: center;
    border: 1px solid #000; vertical-align: middle;
  }
  td.col-date  { background: #fff2cc; font-weight: bold; }
  td.col-heure { background: #b6d7a8; font-weight: bold; }
  td.col-etud  { background: #c9daf8; text-align: left; padding-left: 5px; }
  td.col-filiere { background: #c9daf8; font-weight: bold; }
  td.col-id    { background: #fff; font-weight: bold; }
  td.col-salle { background: #fff; font-weight: bold; }
  tr.separateur-date td {
    height: 6px; padding: 0; border: none;
    background: #fff;
  }
  tr.separateur-date td.sep { border-top: 2px solid #000; }
</style>
</head>
<body>
  <div class="entete">
    <div class="l1">Ecole Nationale des Sciences Appliquées - Al Hoceima</div>
    <div class="l2">Département Mathématiques et Informatique</div>
    <div class="l3">Planning des soutenances des Projets de Fin d'Etude (Première Session)</div>
    <div class="l4">Année Universitaire {$anneeUni}</div>
  </div>
  {$corps}
</body>
</html>
HTML;

        return $this->renderPdf($html, 'A4', 'landscape', 'planning_' . date('Ymd_His') . '.pdf');
    }

    /**
     * @param array<int,array<string,mixed>> $soutenances
     */
    private function construireTableau(array $soutenances): string
    {
        if ($soutenances === []) {
            return '<p style="text-align:center;color:#999">Aucune soutenance planifiée.</p>';
        }

        $groupes = $this->grouperSoutenances($soutenances);
        $html = '<table class="planning"><thead><tr>
            <th style="width:4%">ID</th>
            <th style="width:11%">Encadrant</th>
            <th style="width:11%">Membre de jury 1</th>
            <th style="width:11%">Membre de jury 2</th>
            <th style="width:8%">Date</th>
            <th style="width:5%">Heure</th>
            <th style="width:6%">Salle</th>
            <th style="width:13%">Nom d\'étudiant</th>
            <th style="width:13%">Prénom d\'étudiant</th>
            <th style="width:5%">Filière</th>
        </tr></thead><tbody>';

        $id = 0;
        $datePrecedente = null;

        foreach ($groupes as $groupe) {
            $premier = $groupe[0];
            $dateFmt = $this->formatDateCourte($premier['date_cren']);

            if ($datePrecedente !== null && $datePrecedente !== $premier['date_cren']) {
                $html .= '<tr class="separateur-date"><td colspan="10" class="sep"></td></tr>';
            }
            $datePrecedente = $premier['date_cren'];

            $membres = $this->extraireMembresJury($premier);
            $nb = count($groupe);
            $rowspan = $nb > 1 ? ' rowspan="' . $nb . '"' : '';

            $id++;
            $cellId = '<td class="col-id"' . $rowspan . '>' . $id . '</td>';
            $cellEnc = '<td' . $rowspan . ' style="background:' . $this->couleurProf($membres['encadrant_id']) . '">'
                . $membres['encadrant'] . '</td>';
            $cellJ1 = '<td' . $rowspan . ' style="background:' . $this->couleurProf($membres['jury1_id']) . '">'
                . $membres['jury1'] . '</td>';
            $cellJ2 = '<td' . $rowspan . ' style="background:' . $this->couleurProf($membres['jury2_id']) . '">'
                . $membres['jury2'] . '</td>';
            $cellDate = '<td class="col-date"' . $rowspan . '>' . htmlspecialchars($dateFmt) . '</td>';
            $cellHeure = '<td class="col-heure"' . $rowspan . '>' . htmlspecialchars($this->formatHeure($premier['heure_debut'])) . '</td>';
            $cellSalle = '<td class="col-salle"' . $rowspan . '>' . htmlspecialchars($this->formatSalle((int) $premier['num_salle'])) . '</td>';

            foreach ($groupe as $i => $s) {
                $html .= '<tr>';
                if ($i === 0) {
                    $html .= $cellId . $cellEnc . $cellJ1 . $cellJ2 . $cellDate . $cellHeure . $cellSalle;
                }
                $html .= '<td class="col-etud">' . htmlspecialchars(mb_strtoupper($s['nom_etud'])) . '</td>';
                $html .= '<td class="col-etud">' . htmlspecialchars($this->formatPrenom($s['prenom_etud'])) . '</td>';
                $html .= '<td class="col-filiere">' . htmlspecialchars($s['filiere'] ?? '') . '</td>';
                $html .= '</tr>';
            }
        }

        $html .= '</tbody></table>';
        return $html;
    }

    /**
     * Regroupe les soutenances partageant le même créneau, salle et jury.
     *
     * @param array<int,array<string,mixed>> $soutenances
     * @return array<int,array<int,array<string,mixed>>>
     */
    private function grouperSoutenances(array $soutenances): array
    {
        $groupes = [];
        foreach ($soutenances as $s) {
            $membres = $this->extraireMembresJury($s);
            $cle = implode('|', [
                $s['date_cren'],
                $s['heure_debut'],
                (string) $s['num_salle'],
                (string) $membres['encadrant_id'],
                (string) $membres['jury1_id'],
                (string) $membres['jury2_id'],
            ]);
            $groupes[$cle][] = $s;
        }
        return array_values($groupes);
    }

    /**
     * @param array<string,mixed> $soutenance
     * @return array{encadrant:string,encadrant_id:int,jury1:string,jury1_id:int,jury2:string,jury2_id:int}
     */
    private function extraireMembresJury(array $soutenance): array
    {
        $jury = SessionStore::getJuryParSoutenance((int) $soutenance['id_stnc']);
        $enc = ['nom' => '', 'id' => 0];
        $jurys = [];

        foreach ($jury as $j) {
            $p = SessionStore::getProfesseurById($j['id_prof']);
            if (!$p) {
                continue;
            }
            $nom = $this->formatNomProf($p['nom_prof'], $p['prenom_prof']);
            if ($j['role_jury'] === 'encadrant') {
                $enc = ['nom' => $nom, 'id' => (int) $p['id_prof']];
            } else {
                $jurys[] = ['nom' => $nom, 'id' => (int) $p['id_prof']];
            }
        }

        return [
            'encadrant'    => $enc['nom'],
            'encadrant_id' => $enc['id'],
            'jury1'        => $jurys[0]['nom'] ?? '',
            'jury1_id'     => $jurys[0]['id'] ?? 0,
            'jury2'        => $jurys[1]['nom'] ?? '',
            'jury2_id'     => $jurys[1]['id'] ?? 0,
        ];
    }

    private function couleurProf(int $idProf): string
    {
        if ($idProf <= 0) {
            return '#ffffff';
        }
        if (!isset($this->couleursProf[$idProf])) {
            $index = count($this->couleursProf);
            $this->couleursProf[$idProf] = $index < count(self::PALETTE)
                ? self::PALETTE[$index]
                : $this->genererCouleurEtendue($index - count(self::PALETTE));
        }
        return $this->couleursProf[$idProf];
    }

    /**
     * Génère une couleur pastel supplémentaire une fois la palette de base épuisée.
     * Utilise l'angle d'or pour répartir les teintes de façon uniforme, ce qui
     * garantit des couleurs visuellement distinctes quel que soit le nombre
     * de professeurs (aucune collision possible, contrairement à un modulo).
     */
    private function genererCouleurEtendue(int $index): string
    {
        $teinte = fmod($index * 137.508, 360);
        return $this->hslVersHex($teinte, 55, 80);
    }

    private function hslVersHex(float $h, float $s, float $l): string
    {
        $s /= 100;
        $l /= 100;
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;

        if ($h < 60) {
            [$r, $g, $b] = [$c, $x, 0];
        } elseif ($h < 120) {
            [$r, $g, $b] = [$x, $c, 0];
        } elseif ($h < 180) {
            [$r, $g, $b] = [0, $c, $x];
        } elseif ($h < 240) {
            [$r, $g, $b] = [0, $x, $c];
        } elseif ($h < 300) {
            [$r, $g, $b] = [$x, 0, $c];
        } else {
            [$r, $g, $b] = [$c, 0, $x];
        }

        $r = (int) round(($r + $m) * 255);
        $g = (int) round(($g + $m) * 255);
        $b = (int) round(($b + $m) * 255);

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    private function formatNomProf(string $nom, string $prenom): string
    {
        return htmlspecialchars(mb_strtoupper(trim($nom)) . ' ' . $this->formatPrenom($prenom));
    }

    private function formatPrenom(string $prenom): string
    {
        $prenom = trim($prenom);
        if ($prenom === '') {
            return '';
        }
        return mb_convert_case(mb_strtolower($prenom, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    private function formatDateCourte(string $date): string
    {
        $ts = strtotime($date);
        return $ts ? date('d/m/Y', $ts) : $date;
    }

    private function formatHeure(string $heure): string
    {
        $h = (int) substr($heure, 0, 2);
        return $h . 'h';
    }

    private function formatSalle(int $num): string
    {
        return 'S' . $num . 'A';
    }

    private function anneeUniversitaire(): string
    {
        $mois = (int) date('n');
        $annee = (int) date('Y');
        if ($mois >= 9) {
            return $annee . '/' . ($annee + 1);
        }
        return ($annee - 1) . '/' . $annee;
    }

    private function renderPdf(string $html, string $paper, string $orientation, string $filename): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->setPaper($paper, $orientation);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();
        $filepath = $this->outputDir . $filename;
        file_put_contents($filepath, $dompdf->output());
        return $filepath;
    }
}
