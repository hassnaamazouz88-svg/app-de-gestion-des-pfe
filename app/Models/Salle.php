<?php
declare(strict_types=1);

final class Salle
{
    public static function create(int $numSalle): void
    {
        SessionStore::ajouterSalle($numSalle);
    }

    public static function exists(int $numSalle): bool
    {
        return SessionStore::salleExiste($numSalle);
    }
}

