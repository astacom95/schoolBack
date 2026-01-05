<?php

namespace Database\Seeders;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class TeacherSeeder extends Seeder
{
    public function run(): void
    {
        $teacherUser = User::query()->updateOrCreate(
            ['user_name' => 'teacher.test'],
            [
                'password' => Hash::make('password123'),
                'phone_number' => '+201000000001',
                'role' => 'Teacher',
                'email' => 'teacher@example.com',
            ]
        );

        Teacher::query()->updateOrCreate(
            ['user_id' => $teacherUser->id],
            [
                'date_of_birth' => Carbon::parse('1992-05-10'),
                'full_name' => 'Test Teacher',
                'certificate_path' => 'storage/certificates/teacher_test.pdf',
                'cv_path' => 'storage/cv/teacher_test.pdf',
                'personal_image_path' => 'storage/profile_images/teacher_test.jpg',
                'country' => 'Egypt',
                'state' => 'Cairo',
                'city' => 'Cairo',
                'gender' => 'Male',
            ]
        );
    }
}
