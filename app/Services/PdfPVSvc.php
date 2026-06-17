<?php
declare(strict_types=1);

use Dompdf\Dompdf;
use Dompdf\Options;

class PdfPVSvc
{
    private string $outputDir;

    public function __construct()
    {
        $this->outputDir = __DIR__ . '/../../public/outputs/pdf_pv_eval/';
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
    }

    public function genererUn(int $idStnc): string
    {
        $s = SessionStore::getSoutenanceById($idStnc);
        if (!$s) throw new \InvalidArgumentException("Soutenance introuvable (id=$idStnc).");
        $membres = SessionStore::getJuryParSoutenance($idStnc);
        // Enrichir les membres avec données professeur
        $membresComplets = [];
        foreach ($membres as $j) {
            $p = SessionStore::getProfesseurById($j['id_prof']);
            if ($p) $membresComplets[] = array_merge($j, ['nom_prof' => $p['nom_prof'], 'prenom_prof' => $p['prenom_prof'], 'specialite' => $p['specialite']]);
        }
        $html     = $this->buildHtml($s, $membresComplets);
        $base     = 'PV_' . $s['nom_etud'] . '_' . $s['prenom_etud'] . '_' . $idStnc;
        $filename = preg_replace('/[^A-Za-z0-9_\-]/', '_', $base) . '.pdf';
        return $this->renderPdf($html, $filename);
    }

    public function genererTous(): array
    {
        $fichiers = [];
        foreach (SessionStore::getSoutenances() as $s) {
            $fichiers[] = $this->genererUn((int)$s['id_stnc']);
        }
        return $fichiers;
    }

    private function buildHtml(array $s, array $membres): string
    {
        $etud     = htmlspecialchars(trim($s['nom_etud'] . ' ' . $s['prenom_etud']));
        $sujet    = trim((string)($s['sujet_pfe'] ?? ''));
        $sujetH   = $sujet !== ''
            ? '<span class="filled">'.htmlspecialchars($sujet).'</span>'
            : '<span class="dots">………………………………………………………………………</span>';
        $datePv   = $this->formatDateFr((string)$s['date_cren']);
        $annee    = $this->anneeUniv((string)$s['date_cren']);
        $filiereH = $this->filiereCheckboxes((string)($s['filiere'] ?? ''));

        $encadrant = null; $jurys = [];
        foreach ($membres as $m) {
            if ($m['role_jury'] === 'encadrant') $encadrant = $m;
            else $jurys[] = $m;
        }

        $logoUae   = $this->logoImg('logo_uae.png',   'Université Abdelmalek Essaâdi');
        $logoEnsah = $this->logoImg('logo_ensah.png', 'ENSA Al-Hoceima');
        $encLine   = $this->profLine($encadrant);
        $jury1     = $jurys[0] ?? null;
        $jury2     = $jurys[1] ?? null;
        $signLine  = implode(' &nbsp;&nbsp; ', [$this->profCourt($encadrant), $this->profCourt($jury1), $this->profCourt($jury2)]);
        $rPres  = $this->profRoleLine($encadrant, 'Président');
        $rRap1  = $this->profRoleLine($jury1,     'Rapporteur');
        $rRap2  = $this->profRoleLine($jury2,     'Rapporteur');

        return <<<HTML
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">
<style>
  @page { margin:1.4cm 1.8cm; }
  body  { font-family:DejaVu Sans,sans-serif; font-size:10.5pt; color:#000; line-height:1.35; }
  .logo-row { width:100%; border-collapse:collapse; margin-bottom:6px; }
  .logo-row td { vertical-align:middle; padding:0 4px; }
  .logo-left { width:20%; text-align:left; } .logo-right { width:20%; text-align:right; }
  .logo-center { width:60%; text-align:center; }
  .logo-row img { max-height:72px; max-width:100%; }
  .logo-center .univ  { font-weight:bold; font-size:12pt; text-transform:uppercase; }
  .logo-center .school,.logo-center .dept { font-size:10.5pt; margin-top:2px; }
  .logo-center .title { font-weight:bold; font-size:11pt; margin-top:10px; }
  .logo-center .year  { font-size:10.5pt; margin-top:6px; }
  .block-label { margin:10px 0 2px; font-size:10.5pt; font-weight:bold; }
  .block-value { margin:0 0 4px 14px; font-size:10.5pt; }
  .dots  { letter-spacing:1px; }
  .filled { font-weight:normal; text-decoration:underline; }
  .filiere-table { width:100%; border-collapse:collapse; font-size:10pt; }
  .filiere-title-cell { width:72px; vertical-align:top; padding:2px 10px 2px 0; white-space:nowrap; }
  .filiere-title { font-weight:bold; text-decoration:underline; font-size:10.5pt; }
  .filiere-opt-cell { vertical-align:middle; padding:3px 14px 3px 0; }
  .cb-icon { display:inline-block; width:12px; height:12px; border:1.3px solid #222;
              text-align:center; line-height:11px; font-size:9pt; font-weight:bold;
              margin-right:6px; vertical-align:middle; background:#fff; }
  .cb-icon--checked { background:#222; color:#fff; }
  .filiere-label { vertical-align:middle; font-size:10pt; }
  .filiere-option--active .filiere-label { font-weight:bold; }
  .section-gap { margin-top:12px; }
  .note-label { margin:10px 0 2px; font-size:10.5pt; }
  .note-line  { margin:2px 0 8px 14px; font-size:10.5pt; }
  .date-line  { margin:16px 0 8px; font-size:10.5pt; }
  .sign-title { margin:6px 0 4px; font-size:10.5pt; }
  .sign-line  { margin:6px 0 10px 14px; font-size:10.5pt; }
  .role-line  { margin:5px 0 5px 14px; font-size:10.5pt; }
  .moyenne    { text-align:center; margin-top:18px; font-weight:bold; font-size:11pt; }
  .moyenne-formula { font-weight:normal; font-size:10pt; margin-top:8px; text-align:center; }
  .moyenne-result  { display:inline-block; min-width:60px; border-bottom:1px solid #000; min-height:14px; vertical-align:bottom; }
</style>
</head><body>
<table class="logo-row"><tr>
  <td class="logo-left">$logoUae</td>
  <td class="logo-center">
    <div class="univ">UNIVERSITÉ ABDELMALEK ESSAÂDI</div>
    <div class="school">École Nationale des Sciences Appliquées d'Al-Hoceima</div>
    <div class="dept">Département de Mathématiques et Informatique</div>
    <div class="title">Fiche d'évaluation du Projet de Fin d'Étude</div>
    <div class="year">Année Universitaire : $annee</div>
  </td>
  <td class="logo-right">$logoEnsah</td>
</tr></table>
<p class="block-label">Nom - Prénom de l'élève ingénieur :</p>
<p class="block-value">− <span class="filled">$etud</span></p>
<div style="margin:10px 0 14px">$filiereH</div>
<p class="block-label">Intitulé du rapport :</p>
<p class="block-value">− $sujetH</p>
<p class="block-label section-gap">L'encadrant(e) interne :</p>
<p class="block-value">− $encLine</p>
<p class="block-label">Membres du jury :</p>
<p class="role-line">− $rPres</p>
<p class="role-line">− $rRap1</p>
<p class="role-line">− $rRap2</p>
<div>
  <p class="note-label">Note du Contenu :</p>
  <p class="note-line">C =<span class="dots">………………………………………………………………………</span></p>
  <p class="note-label">Note du Mémoire :</p>
  <p class="note-line">M =<span class="dots">………………………………………………………………………</span></p>
  <p class="note-label">Note de la Soutenance :</p>
  <p class="note-line">S =<span class="dots">………………………………………………………………………</span></p>
</div>
<p class="moyenne">MOYENNE</p>
<p class="moyenne-formula">Moyenne = C×0,5 + M×0,2 + S×0,3 = <span class="moyenne-result">&nbsp;</span></p>
<p class="date-line">Le : <span class="filled">$datePv</span></p>
<p class="sign-title">Signature des membres du jury :</p>
<p class="sign-line">$signLine</p>
</body></html>
HTML;
    }

    private function profLine(?array $p): string
    {
        if (!$p) return 'Pr. <span class="dots">………………………………………………………………………</span>';
        return 'Pr. <span class="filled">'.htmlspecialchars(trim($p['nom_prof'].' '.$p['prenom_prof'])).'</span>';
    }

    private function profCourt(?array $p): string
    {
        if (!$p) return 'Pr. ……………….';
        return 'Pr. '.htmlspecialchars(trim($p['nom_prof'].' '.$p['prenom_prof']));
    }

    private function profRoleLine(?array $p, string $role): string
    {
        return $this->profLine($p) . ' &nbsp;&nbsp;&nbsp; ' . htmlspecialchars($role);
    }

    private function logoImg(string $filename, string $alt): string
    {
        if (!extension_loaded('gd')) return '';
        $path = __DIR__ . '/../../public/assets/images/' . $filename;
        if (!is_file($path)) return '';
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match($ext) { 'jpg','jpeg' => 'image/jpeg', 'gif' => 'image/gif', default => 'image/png' };
        $data = 'data:'.$mime.';base64,'.base64_encode((string)file_get_contents($path));
        return '<img src="'.$data.'" alt="'.htmlspecialchars($alt).'">';
    }

    private function filiereCheckboxes(string $filiere): string
    {
        $f     = mb_strtolower($filiere);
        $isTdi = str_contains($f,'transform')||str_contains($f,'digitale')||str_contains($f,'intelligence')||str_contains($f,'artifici');
        $isData= !$isTdi && (str_contains($f,'donn')||str_contains($f,'data'));
        $isInfo= !$isTdi && !$isData && (str_contains($f,'info')||str_contains($f,'génie')||str_contains($f,'genie'));
        $opt = fn(string $label, bool $active): string =>
            '<span class="filiere-option'.($active?' filiere-option--active':'').'">'
            .'<span class="cb-icon'.($active?' cb-icon--checked':'').'">'.($active?'&#x2713;':'&nbsp;').'</span>'
            .'<span class="filiere-label">'.htmlspecialchars($label).'</span></span>';
        return '<table class="filiere-table"><tr>'
            .'<td class="filiere-title-cell" rowspan="2"><span class="filiere-title">Filière :</span></td>'
            .'<td class="filiere-opt-cell">'.$opt('Ingénierie des Données',$isData).'</td>'
            .'<td class="filiere-opt-cell">'.$opt('Génie Informatique',$isInfo).'</td>'
            .'</tr><tr><td class="filiere-opt-cell" colspan="2">'.$opt('Transformation digitale et IA',$isTdi).'</td></tr></table>';
    }

    private function anneeUniv(string $date): string
    {
        $ts = strtotime($date);
        $y  = $ts ? (int)date('Y',$ts) : (int)date('Y');
        if ($ts && (int)date('n',$ts) < 9) $y--;
        return "$y-".($y+1);
    }

    private function formatDateFr(string $date): string
    {
        $ts = strtotime($date);
        return $ts ? date('d/m/Y',$ts) : '……………………';
    }

    private function renderPdf(string $html, string $filename): string
    {
        if (!extension_loaded('gd')) {
            throw new \RuntimeException('Extension PHP GD requise. Décommentez extension=gd dans php.ini puis redémarrez Apache.');
        }
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();
        $filepath = $this->outputDir . $filename;
        file_put_contents($filepath, $dompdf->output());
        return $filepath;
    }
}
