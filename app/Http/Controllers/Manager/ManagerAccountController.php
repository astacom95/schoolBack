<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Manager;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ManagerAccountController extends Controller
{
    private function authorizedSystemManager(Request $request): ?User
    {
        $requestUser = $request->user();
        if (! $requestUser || $requestUser->role !== 'Manager') {
            return null;
        }

        if ($requestUser->user_name !== 'system.manager') {
            return null;
        }

        return $requestUser;
    }

    private function transformManager(Manager $manager): array
    {
        return [
            'id' => $manager->id,
            'full_name' => $manager->full_name,
            'user_name' => $manager->user?->user_name,
            'phone_number' => $manager->user?->phone_number,
            'email' => $manager->user?->email,
            'date_of_birth' => $manager->date_of_birth,
            'gender' => $manager->gender,
            'created_at' => optional($manager->created_at)->toDateString(),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        if (! $this->authorizedSystemManager($request)) {
            return response()->json(['message' => 'غير مصرح.'], 403);
        }

        $managers = Manager::query()
            ->with('user')
            ->latest('id')
            ->get()
            ->map(fn (Manager $manager) => $this->transformManager($manager))
            ->values();

        return response()->json(['data' => $managers]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! $this->authorizedSystemManager($request)) {
            return response()->json(['message' => 'غير مصرح.'], 403);
        }

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'user_name' => ['required', 'string', 'max:255', 'unique:users,user_name'],
            'password' => ['required', 'string', 'min:6'],
            'phone_number' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'gender' => ['required', 'in:Male,Female'],
            'date_of_birth' => ['required', 'date'],
        ]);

        $manager = DB::transaction(function () use ($data) {
            $user = User::query()->create([
                'user_name' => $data['user_name'],
                'password' => Hash::make($data['password']),
                'phone_number' => $data['phone_number'],
                'role' => 'Manager',
                'email' => $data['email'] ?? null,
            ]);

            return Manager::query()->create([
                'user_id' => $user->id,
                'full_name' => $data['full_name'],
                'date_of_birth' => $data['date_of_birth'],
                'gender' => $data['gender'],
            ]);
        });

        $manager->load('user');

        return response()->json(['data' => $this->transformManager($manager)], 201);
    }

    public function update(Request $request, Manager $manager): JsonResponse
    {
        if (! $this->authorizedSystemManager($request)) {
            return response()->json(['message' => 'غير مصرح.'], 403);
        }

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'user_name' => ['required', 'string', 'max:255', 'unique:users,user_name,' . $manager->user_id],
            'phone_number' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email,' . $manager->user_id],
            'gender' => ['required', 'in:Male,Female'],
            'date_of_birth' => ['required', 'date'],
            'password' => ['nullable', 'string', 'min:6'],
        ]);

        DB::transaction(function () use ($manager, $data) {
            if ($manager->user) {
                $userUpdate = [
                    'user_name' => $data['user_name'],
                    'phone_number' => $data['phone_number'],
                    'email' => $data['email'] ?? null,
                ];
                if (! empty($data['password'])) {
                    $userUpdate['password'] = Hash::make($data['password']);
                }
                $manager->user->update($userUpdate);
            }

            $manager->update([
                'full_name' => $data['full_name'],
                'date_of_birth' => $data['date_of_birth'],
                'gender' => $data['gender'],
            ]);
        });

        $manager->load('user');

        return response()->json(['data' => $this->transformManager($manager)]);
    }

    public function destroy(Request $request, Manager $manager): JsonResponse
    {
        if (! $this->authorizedSystemManager($request)) {
            return response()->json(['message' => 'غير مصرح.'], 403);
        }

        DB::transaction(function () use ($manager) {
            if ($manager->user) {
                $manager->user->delete();
                return;
            }

            $manager->delete();
        });

        return response()->json(['message' => 'تم حذف المدير بنجاح.']);
    }
}
