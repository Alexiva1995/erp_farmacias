<?php

declare(strict_types=1);

namespace App\Enums;

enum ScraperKey: string
{
    case DRONENA = 'dronena';
    case DROCERCA = 'drocerca';
    case MAFARTA = 'mafarta';
    case CRISTMEDICALS = 'cristmedicals';
    case DROMEGA = 'dromega';
    case DROSYMCA = 'drosymca';

    /**
     * Normaliza un identificador heredado (ej. 'mafarta_bot' -> 'mafarta').
     */
    public static function normalize(string $raw): ?self
    {
        $clean = strtolower(trim($raw));
        if (str_ends_with($clean, '_bot')) {
            $clean = substr($clean, 0, -4);
        }

        return self::tryFrom($clean);
    }
}
