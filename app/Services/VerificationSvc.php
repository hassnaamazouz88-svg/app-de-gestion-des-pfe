<?php
declare(strict_types=1);

class VerificationSvc
{
    public function verifierAffectation(): array
    {
        $erreurs = []; $avertissements = [];

        // 1. Répartition équitable
        $profs   = SessionStore::getProfesseursAvecNbEtudiants();
        $total   = array_sum(array_column($profs, 'nb_etudiants'));
        $nbProfs = count($profs);
        if ($nbProfs > 0) {
            $base  = intdiv((int)$total, $nbProfs);
            $reste = (int)$total % $nbProfs;
            foreach ($profs as $p) {
                $nb  = (int)$p['nb_etudiants'];
                $ok  = ($nb === $base) || ($reste > 0 && $nb === $base + 1);
                if (!$ok) {
                    $attendu = $reste > 0 ? "$base ou " . ($base + 1) : (string)$base;
                    $ecart   = abs($nb - $base);
                    $msg     = "Prof {$p['nom_prof']} {$p['prenom_prof']} : $nb étudiant(s), attendu $attendu (écart $ecart).";
                    if ($nb > $base + 1) $erreurs[] = $msg;
                    else $avertissements[] = $msg;
                }
            }
        }

        // 2. Étudiants sans encadrant
        foreach (SessionStore::getEtudiantsSansEncadrant() as $e) {
            $erreurs[] = "Étudiant {$e['nom_etud']} {$e['prenom_etud']} n'a pas d'encadrant.";
        }

        return ['erreurs' => $erreurs, 'avertissements' => $avertissements];
    }

    public function verifierPlanning(): array
    {
        $erreurs = []; $avertissements = [];

        // 0. Étudiants sans soutenance
        foreach (SessionStore::getEtudiantsSansSoutenance() as $e) {
            if ($e['id_prof'] === null) {
                $erreurs[] = "Étudiant {$e['nom_etud']} {$e['prenom_etud']} : pas d'encadrant.";
            } else {
                $erreurs[] = "Étudiant {$e['nom_etud']} {$e['prenom_etud']} : encadré mais sans soutenance.";
            }
        }

        $soutenances = SessionStore::getSoutenances();

        // 1. Vérifier chaque soutenance
        foreach ($soutenances as $s) {
            $idStnc  = $s['id_stnc'];
            $membres = SessionStore::getJuryParSoutenance($idStnc);

            if (count($membres) !== NB_JURY) {
                $erreurs[] = "Soutenance #$idStnc : " . count($membres) . " membre(s) (attendu " . NB_JURY . ").";
            }

            // Conflit salle
            $autreStnc = array_filter(
                SessionStore::getSoutenancesParCreneau($s['id_cren']),
                fn($x) => $x['id_stnc'] !== $idStnc && $x['num_salle'] === $s['num_salle']
            );
            if ($autreStnc) {
                $erreurs[] = "Soutenance #$idStnc : salle {$s['num_salle']} déjà occupée sur ce créneau.";
            }
        }

        // 2. Repos professeurs
        foreach (SessionStore::getProfesseurs() as $p) {
            $idProf = $p['id_prof'];
            $soutsProf = SessionStore::getSoutenancesParProf($idProf);
            usort($soutsProf, fn($a, $b) => strcmp($a['date_cren'].$a['heure_debut'], $b['date_cren'].$b['heure_debut']));
            for ($i = 1, $n = count($soutsProf); $i < $n; $i++) {
                $finPrev   = strtotime($soutsProf[$i-1]['date_cren'].' '.$soutsProf[$i-1]['heure_fin']);
                $debutCurr = strtotime($soutsProf[$i]['date_cren'].' '.$soutsProf[$i]['heure_debut']);
                $gap = ($debutCurr - $finPrev) / 60;
                if ($gap < REPOS_MIN_PROF) {
                    $avertissements[] = "Prof {$p['nom_prof']} {$p['prenom_prof']} : repos insuffisant ({$gap} min).";
                }
            }
        }

        // 3. Durée session
        return ['erreurs' => $erreurs, 'avertissements' => $avertissements];
    }

    public function verifierTout(): array
    {
        $a = $this->verifierAffectation();
        $p = $this->verifierPlanning();
        return [
            'erreurs'        => array_merge($a['erreurs'], $p['erreurs']),
            'avertissements' => array_merge($a['avertissements'], $p['avertissements']),
        ];
    }
}
