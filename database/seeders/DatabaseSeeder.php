<?php

namespace Database\Seeders;

use App\Models\Almacen;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Usuario::firstOrCreate(
            ['username' => 'admin'],
            [
                'password' => Hash::make('admin123'),
                'email' => 'admin@rentaltech.pe',
                'rol' => 'admin',
            ]
        );

        Usuario::firstOrCreate(
            ['username' => 'secretaria'],
            [
                'password' => Hash::make('secretaria123'),
                'email' => 'secretaria@rentaltech.pe',
                'rol' => 'secretaria',
            ]
        );

        Usuario::firstOrCreate(
            ['username' => 'contador'],
            [
                'password' => Hash::make('contador123'),
                'email' => 'contador@rentaltech.pe',
                'rol' => 'contador',
            ]
        );

        Almacen::firstOrCreate(['id' => 1], ['nombre' => 'Almacén 1', 'descripcion' => 'Almacén principal']);
        Almacen::firstOrCreate(['id' => 2], ['nombre' => 'Almacén 2', 'descripcion' => 'Almacén secundario']);

        $this->call(MerchSeeder::class);
    }
}
