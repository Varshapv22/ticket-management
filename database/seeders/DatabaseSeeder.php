<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AgentSeeder::class);

        User::updateOrCreate(
            ['email' => 'user@support.test'],
            [
                'name'     => 'Demo User',
                'role'     => User::ROLE_USER,
                'password' => 'password',
            ]
        );
    }
}
