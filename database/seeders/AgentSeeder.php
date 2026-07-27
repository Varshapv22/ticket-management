<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AgentSeeder extends Seeder
{
    public function run(): void
    {
        $agents = [
            ['name' => 'John Deo',  'email' => 'john@support.test'],
            ['name' => 'Priya Nair',   'email' => 'priya@support.test'],
            ['name' => 'Arun Kumar',   'email' => 'arun@support.test'],
        ];

        foreach ($agents as $agent) {
            User::updateOrCreate(
                ['email' => $agent['email']],
                [
                    'name'     => $agent['name'],
                    'role'     => User::ROLE_AGENT,
                    'password' => 'password',
                ]
            );
        }
    }
}
