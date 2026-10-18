<?php

namespace Database\Seeders\Demo\Support;

use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Générateur pseudo-aléatoire déterministe (graine fixe) : deux exécutions du jeu de démonstration
 * produisent exactement les mêmes données. Isolé de l'état global de PHP (mt_rand, shuffle...) pour
 * que rien d'autre ne puisse décaler la séquence.
 */
class Rng
{
    private Randomizer $randomizer;

    private ?float $gaussEnReserve = null;

    public function __construct(int $graine)
    {
        $this->randomizer = new Randomizer(new Mt19937($graine));
    }

    public function int(int $min, int $max): int
    {
        return $this->randomizer->getInt($min, $max);
    }

    /** Réel uniforme dans [0, 1[. */
    public function float(): float
    {
        return $this->randomizer->getInt(0, PHP_INT_MAX - 1) / PHP_INT_MAX;
    }

    public function between(float $min, float $max): float
    {
        return $min + ($max - $min) * $this->float();
    }

    public function chance(float $probabilite): bool
    {
        return $this->float() < $probabilite;
    }

    /**
     * @template T
     *
     * @param  array<T>  $valeurs
     * @return T
     */
    public function pick(array $valeurs): mixed
    {
        $valeurs = array_values($valeurs);

        return $valeurs[$this->int(0, count($valeurs) - 1)];
    }

    /**
     * Tirage pondéré : ['valeur' => poids, ...].
     */
    public function weighted(array $poids): string|int
    {
        $total = array_sum($poids);
        $tirage = $this->float() * $total;

        foreach ($poids as $valeur => $p) {
            $tirage -= $p;
            if ($tirage < 0) {
                return $valeur;
            }
        }

        return array_key_last($poids);
    }

    /** Loi normale (Box-Muller). */
    public function gauss(float $moyenne = 0.0, float $ecartType = 1.0): float
    {
        if ($this->gaussEnReserve !== null) {
            $z = $this->gaussEnReserve;
            $this->gaussEnReserve = null;

            return $moyenne + $ecartType * $z;
        }

        do {
            $u1 = $this->float();
        } while ($u1 <= 1e-12);
        $u2 = $this->float();

        $r = sqrt(-2.0 * log($u1));
        $this->gaussEnReserve = $r * sin(2 * M_PI * $u2);

        return $moyenne + $ecartType * $r * cos(2 * M_PI * $u2);
    }

    /**
     * @template T
     *
     * @param  array<T>  $valeurs
     * @return array<T>
     */
    public function shuffle(array $valeurs): array
    {
        return $this->randomizer->shuffleArray(array_values($valeurs));
    }

    /** Date aléatoire (Y-m-d) entre deux dates incluses. */
    public function date(string $debut, string $fin): string
    {
        $a = strtotime($debut.' 00:00:00 UTC');
        $b = strtotime($fin.' 00:00:00 UTC');

        return gmdate('Y-m-d', $this->int(min($a, $b), max($a, $b)));
    }

    /** Date-heure aléatoire (Y-m-d H:i:s) pendant les heures ouvrées d'un jour donné. */
    public function heureOuvree(string $jour, int $heureMin = 7, int $heureMax = 18): string
    {
        return sprintf('%s %02d:%02d:%02d', $jour, $this->int($heureMin, $heureMax), $this->int(0, 59), $this->int(0, 59));
    }

    /** Chaîne alphanumérique majuscule (références, numéros de certificat). */
    public function code(int $longueur, string $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'): string
    {
        $s = '';
        for ($i = 0; $i < $longueur; $i++) {
            $s .= $alphabet[$this->int(0, strlen($alphabet) - 1)];
        }

        return $s;
    }
}
