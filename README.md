# GestionPFE — Application PHP de gestion des soutenances

## Prérequis

- PHP ≥ 8.1
- MySQL / MariaDB
- Composer

## Installation

```bash
# 1. Cloner / décompresser le projet
cd gestion-pfe

# 2. Installer les dépendances
composer require phpoffice/phpspreadsheet tecnickcom/tcpdf

# 3. Créer la base de données
mysql -u root -p < config/schema.sql

# 4. Configurer la connexion BDD
#    Éditer config/database.php  ($host, $dbname, $user, $password)

# 5. Lancer le serveur de développement
php -S localhost:8000 -t .
```

> Attention : `-t` doit être suivi du dossier racine (par exemple `.`). Ne lancez pas `php -S localhost:8000 -t` sans chemin.

Ouvrir http://localhost:8000

Si vous servez le projet avec Apache/XAMPP à partir de `htdocs/gestion_pfe`, ouvrez plutôt :

http://localhost/gestion_pfe

## Flux d'utilisation

| Étape | URL | Action |
|-------|-----|--------|
| 1 | `/import` | Importer étudiants et professeurs (XLSX/CSV) |
| 2 | `/affectation` | Affecter automatiquement les encadrants |
| 3 | `/planning` | Générer le planning des soutenances |
| 4 | `/pv` | Télécharger les procès-verbaux PDF |
| 5 | `/verification` | Vérifier toutes les contraintes |

## Structure

```
gestion-pfe/
├── index.php               Point d'entrée unique
├── config/
│   ├── database.php        Singleton PDO
│   ├── constraints.php     Constantes métier
│   ├── routes.php          Routeur + autoload
│   └── schema.sql          DDL base de données
├── app/
│   ├── Models/             Etudiant, Professeur, Soutenance, Creneau, Salle, Participer
│   ├── Services/           AffectationSvc, PlanningSvc, PDF*, Statistics*, Verification*
│   ├── Controllers/        Import, Affectation, Planning, PV, Dashboard, Verif
│   └── Utils/              ConstraintChecker
├── views/                  Vues PHP + partial navbar
└── public/
    ├── assets/css/         style.css
    ├── assets/js/          app.js
    └── outputs/            PDFs générés
```

## Constantes métier (`config/constraints.php`)

| Constante | Valeur | Description |
|-----------|--------|-------------|
| `DUREE_SOUTENANCE` | 60 min | Durée d'un créneau |
| `NB_JOURS_SESSION` | 3 | Jours de session |
| `NB_JURY` | 3 | Membres du jury |
| `MIN_INFORMATICIENS_JURY` | 2 | Informaticiens minimum |
| `REPOS_MIN_PROF` | 60 min | Repos entre deux soutenances |
| `ENCADREMENT_MIN/MAX` | 3–4 | Charge d'encadrement par prof |
