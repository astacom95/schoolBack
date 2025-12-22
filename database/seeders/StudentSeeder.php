<?php

namespace Database\Seeders;

use App\Models\Level;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('ar_SA');

        // Ensure we have base levels and classes to satisfy foreign keys.
        $levels = collect([
            'الأول الثانوي',
            'الثاني الثانوي',
            'الثالث الثانوي',
        ])->map(function (string $name) {
            return Level::firstOrCreate(['name' => $name]);
        });

        $classesByLevel = [];
        foreach ($levels as $level) {
            $classesByLevel[$level->id] = collect([
                'أ',
                'ب',
                'ج',
            ])->map(function (string $suffix) use ($level) {
                return SchoolClass::firstOrCreate(
                    ['name' => "{$level->name} - {$suffix}", 'level_id' => $level->id],
                    ['number_of_subjects' => 8]
                );
            });
        }

        $genders = ['Male', 'Female'];
        $states = ['الرياض', 'جدة', 'الدمام', 'مكة', 'المدينة', 'أبها'];
        $cities = ['الرياض', 'جدة', 'الدمام', 'مكة', 'المدينة', 'أبها', 'تبوك', 'حائل'];

        foreach (range(1, 100) as $i) {
            $gender = $faker->randomElement($genders);
            $level = $levels->random();
            $class = $classesByLevel[$level->id]->random();

            $user = User::create([
                'user_name' => "student{$i}_" . uniqid(),
                'password' => Hash::make('password123'),
                'phone_number' => $faker->unique()->e164PhoneNumber(),
                'role' => 'Student',
                'email' => $faker->unique()->safeEmail(),
            ]);

            Student::create([
                'user_id' => $user->id,
                'date_of_birth' => Carbon::today()->subYears(rand(15, 18))->subDays(rand(0, 365)),
                'full_name' => $faker->name($gender === 'Male' ? 'male' : 'female'),
                'country' => 'السعودية',
                'state' => $faker->randomElement($states),
                'city' => $faker->randomElement($cities),
                'gender' => $gender,
                'level_id' => $level->id,
                'class_id' => $class->id,
                'certificate_path' => "storage/certificates/student_{$i}.pdf",
                'personal_image_path' => "storage/profile_images/student_{$i}.jpg",
                'guardian_name' => $faker->name(),
                'guardian_relationship' => $faker->randomElement(['Father', 'Mother', 'Guardian']),
                'student_phone_number' => $faker->phoneNumber(),
                'guardian_phone_number' => $faker->phoneNumber(),
                'guardian_email' => $faker->safeEmail(),
                'guardian_address' => $faker->address(),
            ]);
        }
    }
}
