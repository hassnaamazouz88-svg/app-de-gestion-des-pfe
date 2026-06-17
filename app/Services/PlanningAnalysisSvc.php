<?php
declare(strict_types=1);

final class PlanningAnalysisSvc
{
    /**
     * @param array<int,string> $heuresDebut Liste d'heures "HH:MM"
     * @param array<int,int> $salles Liste de numéros de salle
     * @return array{
     *   besoin:int,
     *   jours:int,
     *   creneaux_par_jour:int,
     *   creneaux:int,
     *   salles:int,
     *   capacite:int,
     *   jours_min:int,
     *   salles_min:int,
     *   occupation:float,
     *   niveau: 'ok'|'warn'|'bad',
     *   avertissements: array<int,string>,
     *   recommandations: array<int,string>
     * }
     */
    public function analyser(string $dateDebut, string $dateFin, array $heuresDebut, array $salles): array
    {
        $besoin = count(array_filter(SessionStore::getEtudiants(), fn($e) => $e['id_prof'] !== null));
        $jours  = $this->joursInclusifs($dateDebut, $dateFin);
        $creneauxParJour = count($heuresDebut);
        $nbSalles = count($salles);

        $creneaux = max(0, $jours) * max(0, $creneauxParJour);
        $capacite = $creneaux * $nbSalles;

        $occupation = ($capacite > 0) ? ($besoin / $capacite) : 1.0;

        $avertissements = [];
        $recommandations = [];

        if ($creneauxParJour === 0) {
            $avertissements[] = "Aucune plage horaire sélectionnée : 0 créneau disponible.";
            $recommandations[] = "Ajoutez des heures de début (ex: 09:00, 10:00, 11:00).";
        }
        if ($nbSalles === 0) {
            $avertissements[] = "Aucune salle sélectionnée : capacité nulle.";
            $recommandations[] = "Sélectionnez au moins une salle.";
        }
        if ($jours <= 0) {
            $avertissements[] = "Période invalide : aucune journée couverte.";
            $recommandations[] = "Vérifiez les dates de début et de fin.";
        }

        if ($this->hasCreneauxConsecutifsSansRepos($heuresDebut)) {
            $avertissements[] = "Des créneaux horaires se suivent sans pause suffisante : un professeur ne peut pas enchaîner deux soutenances sur des heures consécutives (repos minimal de " . REPOS_MIN_PROF . " min après chaque soutenance de " . DUREE_SOUTENANCE . " min).";
            $recommandations[] = "Espacez les heures de début (ex: 09:00, 11:00, 14:00, 16:00 au lieu de 09:00, 10:00, 11:00).";
        }

        if ($capacite >= $besoin && $besoin > 0 && $occupation >= 0.85) {
            $avertissements[] = "Capacité théorique proche du besoin : la planification réelle peut être limitée par les jurys et le repos des professeurs.";
        }

        $joursMin = 0;
        if ($creneauxParJour > 0 && $nbSalles > 0) {
            $capJour = $creneauxParJour * $nbSalles;
            $joursMin = (int) ceil($besoin / max(1, $capJour));
        }

        $sallesMin = 0;
        if ($creneaux > 0) {
            $sallesMin = (int) ceil($besoin / max(1, $creneaux));
        }

        $niveau = 'ok';
        if ($capacite < $besoin) $niveau = 'bad';
        elseif ($occupation >= 0.85) $niveau = 'warn';

        if ($capacite > 0 && $capacite < $besoin) {
            $nonPlanifiables = $besoin - $capacite;
            $avertissements[] = "Ressources insuffisantes : capacité $capacite pour $besoin soutenance(s). $nonPlanifiables ne pourront pas être planifiées.";
            $recommandations[] = "Augmentez le nombre de jours (minimum estimé : $joursMin).";
            $recommandations[] = "Augmentez le nombre de salles (minimum estimé : $sallesMin).";
            $recommandations[] = "Élargissez les plages horaires (créneaux/jour actuellement : $creneauxParJour).";
        } elseif ($capacite > 0 && $occupation >= 0.85) {
            $avertissements[] = "Capacité proche du besoin : taux d'occupation prévisionnel " . (int) round($occupation * 100) . "%.";
            $recommandations[] = "Ajoutez une marge (un jour, une salle, ou quelques créneaux) pour réduire le risque d'échecs liés aux contraintes (jury/repos).";
        }

        return [
            'besoin' => $besoin,
            'jours' => $jours,
            'creneaux_par_jour' => $creneauxParJour,
            'creneaux' => $creneaux,
            'salles' => $nbSalles,
            'capacite' => $capacite,
            'jours_min' => $joursMin,
            'salles_min' => $sallesMin,
            'occupation' => $occupation,
            'niveau' => $niveau,
            'avertissements' => $avertissements,
            'recommandations' => array_values(array_unique($recommandations)),
        ];
    }

    private function joursInclusifs(string $d, string $f): int
    {
        $t0 = strtotime($d . ' 12:00:00');
        $t1 = strtotime($f . ' 12:00:00');
        return (!$t0 || !$t1 || $t1 < $t0) ? 0 : (int) round(($t1 - $t0) / 86400) + 1;
    }

    /**
     * @param array<int,string> $heuresDebut
     */
    private function hasCreneauxConsecutifsSansRepos(array $heuresDebut): bool
    {
        $heures = $heuresDebut;
        sort($heures);
        for ($i = 1, $n = count($heures); $i < $n; $i++) {
            $prev = strtotime('2000-01-01 ' . $heures[$i - 1]);
            $curr = strtotime('2000-01-01 ' . $heures[$i]);
            if (!$prev || !$curr) {
                continue;
            }
            $ecartMinutes = (int) round(($curr - $prev) / 60);
            if ($ecartMinutes > 0 && $ecartMinutes <= DUREE_SOUTENANCE) {
                return true;
            }
        }
        return false;
    }
}

