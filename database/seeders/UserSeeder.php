<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear Administrador
        User::create([
            'name' => 'Administrador',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        // Crear Cliente de prueba
        User::create([
            'name' => 'Cliente Demo',
            'email' => 'cliente@example.com',
            'password' => Hash::make('password123'),
            'role' => 'customer',
            'email_verified_at' => now(),
        ]);

        // Crear más clientes de prueba (opcional)
        User::factory()->count(10)->create([
            'role' => 'customer'
        ]);
    }
}
