<?php
declare(strict_types=1);

final class Etudiant
{
    /**
     * @param array{nom_etud:string, prenom_etud:string, filiere:string, email?:string, sujet_pfe?:?string, langue_pfe?:?string} $data
     */
    public static function create(array $data): int
    {
        return SessionStore::ajouterEtudiant($data);
    }

    public static function findByEmail(string $email): ?array
    {
        return SessionStore::getEtudiantByEmail($email);
    }
}

