# GestionPFE — Application PHP de gestion des soutenances

## Prérequis

- PHP ≥ 8.1
- Apache (XAMPP ou équivalent)
- Composer

> Aucune base de données requise. Toutes les données sont stockées en session PHP (`$_SESSION`) via `SessionStore`.

## Installation

```bash
# 1. Décompresser le projet dans le dossier htdocs de XAMPP
#    Résultat attendu : htdocs/gestion_pfe/

# 2. Installer les dépendances
cd htdocs/gestion_pfe
composer install
```

Ouvrir ensuite dans le navigateur :

http://localhost/gestion_pfe

> **Attention :** les données ne persistent que le temps de la session PHP. Fermer le navigateur ou redémarrer Apache efface toutes les données importées.

## Flux d'utilisation

1. **Import** (`/import`)
   Charger le fichier XLSX/CSV contenant les étudiants et les professeurs.

2. **Affectation** (`/affectation`)
   Lancer l'affectation automatique des encadrants et la composition des jurys.

3. **Planning** (`/planning`)
   Analyser les disponibilités puis générer le planning des soutenances.

4. **Procès-verbaux** (`/pv`)
   Générer les PDFs de procès-verbal pour chaque étudiant.

5. **Vérification** (`/verification`)
   Contrôler que toutes les contraintes métier sont respectées avant la session.

## Structure
gestion_pfe/

├── index.php               Point d'entrée unique (session_start + routeur)

├── composer.json           Dépendances PHP

├── .htaccess               Réécriture d'URL Apache (mod_rewrite)

├── config/

│   ├── constraints.php     Constantes métier

│   └── routes.php          Routeur + autoload des classes

├── app/

│   ├── Models/             Etudiant, Professeur, Salle

│   ├── Services/           AffectationSvc, PlanningSvc, PlanningAnalysisSvc,

│   │                       ExcelImportSvc, SessionStore, StatisticsSvc,

│   │                       VerificationSvc, PdfAffectationSvc,

│   │                       PdfPlanningSvc, PdfPVSvc

│   ├── Controllers/        ImportCtrl, AffectationCtrl, PlanningCtrl,

│   │                       PVCtrl, DashboardCtrl, VerifCtrl

│   └── Utils/              ConstraintChecker

├── views/

│   ├── affectation.view.php

│   ├── dashboard.view.php

│   ├── import.view.php

│   ├── planning.view.php

│   ├── pv.view.php

│   ├── verification.view.php

│   └── partials/           head.php, navbar.php, main_open.php, footer.php

└── public/

├── assets/css/         style.css

├── assets/js/          app.js

├── assets/images/      logo_ensah.png, logo_uae.png

└── outputs/

├── pdf_affectation/    PDFs d'affectation générés

├── pdf_planning/       PDFs de planning générés

└── pdf_pv_eval/        PDFs de procès-verbaux générés

## Dépendances (`composer.json`)

| Package                    | Version | Usage |
|----------------------------|---------|-------|
| `phpoffice/phpspreadsheet` | ^2.0    | Import des fichiers XLSX/CSV |
| `dompdf/dompdf`            | ^2.0    | Génération des PDFs |

## Constantes métier (`config/constraints.php`)

| Constante                  | Valeur | Description |
|----------------------------|--------|-------------|
| `DUREE_SOUTENANCE`         |60 min | Durée d'un créneau de soutenance |
| `NB_JURY`                  | 3 | Membres du jury par soutenance |
| `MIN_INFORMATICIENS_JURY`  | 2 | Informaticiens minimum dans le jury |
| `REPOS_MIN_PROF`           | 60 min | Repos entre deux soutenances pour un même prof |
| `SPECIALITE_INFORMATIQUE`  | `'Informatique'` | Libellé de la spécialité informatique |
| `SPECIALITE_ANGLAIS`       | `'Anglais'` | Libellé de la spécialité Anglais |
| `LANGUE_ANGLAIS`           | `'Anglais'` | Valeur de la colonne `langue_pfe` pour les soutenances en anglais |
