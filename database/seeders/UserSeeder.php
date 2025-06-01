<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'SSL Shop Admin',
            'email' => 'admin@ssl-shop.jp',
            'password' => Hash::make('password123'),
            'company_name' => 'SSL Shop Inc.',
        ]);
    }
}
