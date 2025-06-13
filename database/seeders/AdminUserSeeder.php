<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Admin',
            'email' => 'SarebanK@email.com',
            'password' => Hash::make('X(X>4MLeOt+3^zs6z>6]{Y8ld'),
        ]);
    }
}
