<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) ($request->input('per_page') ?? 20);
        $q = (string) ($request->input('q') ?? '');
        $isActive = $request->input('is_active');

        $query = User::query()->orderByDesc('id');

        if ($q !== '') {
            $query->where(function ($qBuilder) use ($q) {
                $qBuilder->where('name', 'like', '%' . $q . '%')
                    ->orWhere('email', 'like', '%' . $q . '%');
            });
        }

        if ($isActive !== null && $isActive !== '') {
            $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN));
        }

        $users = $query->paginate($perPage);

        return response()->json([
            'status' => true,
            'data' => $users,
        ]);
    }

    public function activeCount(Request $request)
    {
        $count = User::where('is_active', true)->count();

        return response()->json([
            'status' => true,
            'data' => [
                'active' => (int) $count,
            ],
        ]);
    }

    public function totalCount(Request $request)
    {
        $count = User::count();

        return response()->json([
            'status' => true,
            'data' => [
                'total' => (int) $count,
            ],
        ]);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $user = User::findOrFail($id);
        $user->update(['is_active' => (bool) $data['is_active']]);

        return response()->json([
            'status' => true,
            'message' => 'Status user diperbarui',
            'data' => [
                'id' => $user->id,
                'is_active' => (bool) $user->is_active,
            ],
        ]);
    }
}
