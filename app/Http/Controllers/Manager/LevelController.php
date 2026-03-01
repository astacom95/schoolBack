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

    public function update(Request $request, Level $level)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'classes' => ['required', 'array', 'min:1'],
            'classes.*.id' => ['nullable', 'integer', 'exists:classes,id'],
            'classes.*.name' => ['required', 'string', 'max:255'],
            'classes.*.number_of_subjects' => ['nullable', 'integer', 'min:0'],
        ]);

        $updatedLevel = DB::transaction(function () use ($data, $level) {
            $level->update([
                'name' => $data['name'],
            ]);

            $existingClassIds = $level->classes()->pluck('id')->all();
            $incomingClassIds = collect($data['classes'])
                ->pluck('id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->all();

            foreach ($data['classes'] as $classData) {
                $classId = isset($classData['id']) ? (int) $classData['id'] : null;

                if ($classId && in_array($classId, $existingClassIds, true)) {
                    SchoolClass::where('id', $classId)
                        ->where('level_id', $level->id)
                        ->update([
                            'name' => $classData['name'],
                            'number_of_subjects' => $classData['number_of_subjects'] ?? 0,
                        ]);
                    continue;
                }

                SchoolClass::create([
                    'name' => $classData['name'],
                    'number_of_subjects' => $classData['number_of_subjects'] ?? 0,
                    'level_id' => $level->id,
                ]);
            }

            $classIdsToDelete = array_diff($existingClassIds, $incomingClassIds);
            if (!empty($classIdsToDelete)) {
                SchoolClass::where('level_id', $level->id)
                    ->whereIn('id', $classIdsToDelete)
                    ->delete();
            }

            return $level->load('classes');
        });

        return response()->json(['data' => $updatedLevel]);
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
