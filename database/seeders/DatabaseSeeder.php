<?php

namespace Database\Seeders;

use App\Models\Manager;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $managerUser = User::query()->create([
            'user_name' => 'system.manager',
            'password' => Hash::make('password123'),
            'phone_number' => '+201000000000',
            'role' => 'Manager',
            'email' => 'manager@example.com',
        ]);

        Manager::query()->create([
            'user_id' => $managerUser->id,
            'date_of_birth' => Carbon::parse('1990-01-01'),
            'full_name' => 'System Manager',
            'gender' => 'Male',
        ]);

        $this->call(TeacherSeeder::class);
        $this->call(StudentSeeder::class);
    }
}
