<?php

namespace Database\Seeders;

use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Conta de demonstração para rodar localmente: demo@lectta.test / password.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'demo@lectta.test'],
            ['name' => 'Demo', 'password_hash' => password_hash('password', PASSWORD_DEFAULT)],
        );

        Subject::firstOrCreate(['user_id' => $user->id, 'name' => 'Geral']);
    }
}
