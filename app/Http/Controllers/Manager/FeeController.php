<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Fee;
use App\Models\SchoolClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeeController extends Controller
{
    protected function transformFee(Fee $fee): array
    {
        return [
            'id' => $fee->id,
            'class_id' => $fee->class_id,
            'class_name' => $fee->classroom->name ?? null,
            'level_id' => $fee->classroom->level_id ?? null,
            'total_fee' => $fee->total_fee,
            'minimum_fee' => $fee->minimum_fee,
        ];
    }

    public function index()
    {
        $fees = Fee::with('classroom')->latest('id')->get()->map(
            fn (Fee $fee) => $this->transformFee($fee)
        );

        return response()->json(['data' => $fees]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'class_id' => ['required', 'exists:classes,id'],
            'total_fee' => ['required', 'numeric', 'min:0'],
            'minimum_fee' => ['required', 'numeric', 'min:0', 'lte:total_fee'],
        ]);

        $class = SchoolClass::find($data['class_id']);

        if (!$class) {
            return response()->json(['message' => 'الفصل غير موجود.'], 404);
        }

        // Prevent duplicate fee for the same class
        $exists = Fee::where('class_id', $data['class_id'])->first();
        if ($exists) {
            return response()->json(['message' => 'تم تعيين رسوم لهذا الفصل مسبقاً.'], 422);
        }

        $fee = DB::transaction(function () use ($data) {
            return Fee::create($data)->load('classroom');
        });

        return response()->json([
            'data' => $this->transformFee($fee),
        ], 201);
    }

    public function update(Request $request, Fee $fee)
    {
        $data = $request->validate([
            'class_id' => ['required', 'exists:classes,id'],
            'total_fee' => ['required', 'numeric', 'min:0'],
            'minimum_fee' => ['required', 'numeric', 'min:0', 'lte:total_fee'],
        ]);

        $class = SchoolClass::find($data['class_id']);

        if (!$class) {
            return response()->json(['message' => 'الفصل غير موجود.'], 404);
        }

        $exists = Fee::where('class_id', $data['class_id'])
            ->where('id', '!=', $fee->id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'تم تعيين رسوم لهذا الفصل مسبقاً.'], 422);
        }

        $fee = DB::transaction(function () use ($fee, $data) {
            $fee->update($data);

            return $fee->fresh()->load('classroom');
        });

        return response()->json([
            'data' => $this->transformFee($fee),
        ]);
    }

    public function destroy(Fee $fee)
    {
        $fee->delete();
        return response()->json(['message' => 'Fee deleted']);
    }
}
