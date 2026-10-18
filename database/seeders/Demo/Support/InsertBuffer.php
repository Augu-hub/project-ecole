<?php

namespace Database\Seeders\Demo\Support;

use Illuminate\Support\Facades\DB;

/**
 * Tampon d'insertion : accumule les lignes et les écrit par lots de taille fixe.
 */
class InsertBuffer
{
    private array $lignes = [];

    private int $total = 0;

    public function __construct(private string $table, private int $taille) {}

    public function add(array $ligne): void
    {
        $this->lignes[] = $ligne;

        if (count($this->lignes) >= $this->taille) {
            $this->flush();
        }
    }

    public function flush(): int
    {
        if ($this->lignes) {
            DB::table($this->table)->insert($this->lignes);
            $this->total += count($this->lignes);
            $this->lignes = [];
        }

        return $this->total;
    }

    public function total(): int
    {
        return $this->total + count($this->lignes);
    }
}
