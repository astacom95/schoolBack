<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Level;
use App\Models\SchoolClass;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SubjectController extends Controller
{
    public function index()
    {
        $subjects = Subject::with(['level', 'classroom'])->latest('id')->get()->map(function (Subject $subject) {
            $bookPdfUrl = $subject->book_pdf ? Storage::url($subject->book_pdf) : null;
            $bookThumbUrl = $subject->book_thumbnail ? Storage::url($subject->book_thumbnail) : null;
            return [
                'id' => $subject->id,
                'name' => $subject->name,
                'level_id' => $subject->level_id,
                'level_name' => $subject->level->name ?? null,
                'class_id' => $subject->class_id,
                'class_name' => $subject->classroom->name ?? null,
                'total_lessons' => $subject->total_lessons,
                'total_degree' => $subject->total_degree,
                'book_pdf' => $bookPdfUrl,
                'book_thumbnail' => $bookThumbUrl,
            ];
        });

        return response()->json(['data' => $subjects]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'level_id' => ['required', 'exists:levels,id'],
            'class_id' => ['required', 'exists:classes,id'],
            'total_lessons' => ['required', 'integer', 'min:0'],
            'total_degree' => ['required', 'integer', 'min:0'],
            'book_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:102400'], // up to ~100MB
            'book_thumbnail' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'], // up to ~10MB
        ]);

        // Ensure the class belongs to the level.
        $class = SchoolClass::where('id', $data['class_id'])
            ->where('level_id', $data['level_id'])
            ->first();

        if (!$class) {
            return response()->json(['message' => 'الصف لا ينتمي إلى المستوى المحدد'], 422);
        }

        $subject = DB::transaction(function () use ($data, $request) {
            if ($request->hasFile('book_pdf')) {
                $data['book_pdf'] = $request->file('book_pdf')->store('subjects/books', 'public');
            }
            if ($request->hasFile('book_thumbnail')) {
                $data['book_thumbnail'] = $request->file('book_thumbnail')->store('subjects/thumbnails', 'public');
            }

            return Subject::create($data);
        });

        $subject->load(['level', 'classroom']);
        $bookPdfUrl = $subject->book_pdf ? Storage::url($subject->book_pdf) : null;
        $bookThumbUrl = $subject->book_thumbnail ? Storage::url($subject->book_thumbnail) : null;

        return response()->json([
            'data' => [
                'id' => $subject->id,
                'name' => $subject->name,
                'level_id' => $subject->level_id,
                'level_name' => $subject->level->name ?? null,
                'class_id' => $subject->class_id,
                'class_name' => $subject->classroom->name ?? null,
                'total_lessons' => $subject->total_lessons,
                'total_degree' => $subject->total_degree,
                'book_pdf' => $bookPdfUrl,
                'book_thumbnail' => $bookThumbUrl,
            ],
        ], 201);
    }

    public function destroy(Subject $subject)
    {
        $subject->delete();
        return response()->json(['message' => 'Subject deleted']);
    }
}
