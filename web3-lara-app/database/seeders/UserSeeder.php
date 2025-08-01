<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use function Psy\bin;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::insert([
            [
                'user_id' => 'user_' . Str::random(10),
                'username' => 'fey',
                'wallet_address' => '0x49F737A113Ce35Afa3eafB03c9D3322eBA19c493',
                'nonce' => bin2hex(random_bytes(16)),
                'role' => 'admin',
                'last_login' => now(),
            ],
            [
                'user_id' => 'user_' . Str::random(10),
                'username' => 'fei',
                'wallet_address' => '0x97aE9A558357E615A58DBd5dC42a2A18bB1e7947',
                'nonce' => bin2hex(random_bytes(16)),
                'role' => 'community',
                'last_login' => now(),
            ],
        ]);
    }
}
