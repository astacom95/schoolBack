<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Level;
use App\Models\SchoolClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LevelController extends Controller
{
    public function index()
    {
        $levels = Level::with('classes')->latest('id')->get();

        return response()->json(['data' => $levels]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'classes' => ['required', 'array', 'min:1'],
            'classes.*.name' => ['required', 'string', 'max:255'],
            'classes.*.number_of_subjects' => ['nullable', 'integer', 'min:0'],
        ]);

        $level = DB::transaction(function () use ($data) {
            $level = Level::create([
                'name' => $data['name'],
            ]);

            foreach ($data['classes'] as $classData) {
                SchoolClass::create([
                    'name' => $classData['name'],
                    'number_of_subjects' => $classData['number_of_subjects'] ?? 0,
                    'level_id' => $level->id,
                ]);
            }

            return $level->load('classes');
        });

        return response()->json(['data' => $level], 201);
    }

    public function destroy(Level $level)
    {
        DB::transaction(function () use ($level) {
            // Remove related classes; subjects will cascade via DB constraints.
            $level->classes()->delete();
            $level->delete();
        });

        return response()->json(['message' => 'Level deleted']);
    }
}
