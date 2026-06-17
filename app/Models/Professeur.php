<?php
declare(strict_types=1);

final class Professeur
{
    /**
     * @param array{nom_prof:string, prenom_prof:string, specialite:string} $data
     */
    public static function create(array $data): int
    {
        return SessionStore::ajouterProfesseur($data);
    }
}

