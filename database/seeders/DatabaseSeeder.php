<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Ejecuta los seeders principales de la aplicación.
     */
    public function run(): void
    {
        $this->call([
            SecurityEventSeeder::class,
        ]);
    }
}
