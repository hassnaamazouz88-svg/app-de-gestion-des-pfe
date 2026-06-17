<?php
declare(strict_types=1);

use Dompdf\Dompdf;
use Dompdf\Options;

class PdfAffectationSvc
{
    private string $outputDir;

    public function __construct()
    {
        $this->outputDir = __DIR__ . '/../../public/outputs/pdf_affectation/';
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
    }

    public function generer(): string
    {
        $svc      = new AffectationSvc();
        $resultat = $svc->getResultat();

        $lignes = '';
        foreach ($resultat as $item) {
            $prof = $item['prof'];
            $nomProf = htmlspecialchars($prof['nom_prof'] . ' ' . $prof['prenom_prof']);
            $spec    = htmlspecialchars($prof['specialite'] ?? '');
            $nb      = count($item['etudiants']);

            $lignesEtud = '';
            foreach ($item['etudiants'] as $e) {
                $nomEtud  = htmlspecialchars($e['nom_etud'] . ' ' . $e['prenom_etud']);
                $filiere  = htmlspecialchars($e['filiere'] ?? '');
                $sujet    = htmlspecialchars($e['sujet_pfe'] ?? '—');
                $lignesEtud .= "<tr><td>$nomEtud</td><td>$filiere</td><td>$sujet</td></tr>";
            }

            $lignes .= "
            <div class='bloc-prof'>
                <div class='prof-header'>
                    Pr. $nomProf <span class='spec'>[$spec]</span>
                    <span class='badge'>$nb étudiant(s)</span>
                </div>
                <table><thead><tr><th>Étudiant</th><th>Filière</th><th>Sujet PFE</th></tr></thead>
                <tbody>$lignesEtud</tbody></table>
            </div>";
        }

        $html = "<!DOCTYPE html><html lang='fr'><head><meta charset='UTF-8'>
        <style>
            body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #2c3e50; }
            h1   { text-align:center; font-size:14px; margin-bottom:4px; }
            .sub { text-align:center; font-size:9px; color:#777; margin-bottom:14px; }
            .bloc-prof { margin-bottom:14px; page-break-inside: avoid; }
            .prof-header { background:#2c3e50; color:#fff; padding:5px 8px; font-size:11px;
                           font-weight:bold; border-radius:3px; margin-bottom:3px; }
            .spec  { font-weight:normal; font-size:9px; }
            .badge { float:right; background:#e74c3c; border-radius:3px; padding:1px 5px; font-size:9px; }
            table  { width:100%; border-collapse:collapse; }
            th     { background:#34495e; color:#fff; padding:4px 6px; font-size:9px; }
            td     { padding:3px 6px; border-bottom:1px solid #dde; font-size:9px; }
        </style></head><body>
        <h1>Affectation des encadrants PFE</h1>
        <p class='sub'>Généré le " . date('d/m/Y à H:i') . "</p>
        $lignes
        </body></html>";

        return $this->renderPdf($html, 'A4', 'portrait', 'affectation_' . date('Ymd_His') . '.pdf');
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
