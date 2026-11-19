<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Retire the old demo accounts when this seeder is re-run.
        User::whereIn('email', ['admin@bytestock.test', 'test@example.com'])->delete();

        User::updateOrCreate(
            ['email' => 'winbert@gmail.com'],
            [
                'name' => 'Winbert',
                'password' => Hash::make('winbert906'),
            ],
        );
    }
}
