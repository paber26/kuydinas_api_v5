<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TopupPackage;
use Illuminate\Http\Request;

class TopupPackageController extends Controller
{
    public function index(Request $request)
    {
        $query = TopupPackage::query();

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('q')) {
            $q = trim((string) $request->input('q'));
            if ($q !== '') {
                $query->where('name', 'like', '%' . $q . '%');
            }
        }

        $packages = $query
            ->orderBy('price')
            ->paginate(20);

        return response()->json([
            'status' => true,
            'data' => $packages,
        ]);
    }

    public function show($id)
    {
        $package = TopupPackage::findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $package,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateStoreData($request);
        $data['is_active'] = $data['is_active'] ?? true;

        $package = TopupPackage::create($data);

        return response()->json([
            'status' => true,
            'message' => 'Paket top up berhasil dibuat',
            'data' => $package,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $package = TopupPackage::findOrFail($id);
        $data = $this->validateUpdateData($request);

        $package->update($data);

        return response()->json([
            'status' => true,
            'message' => 'Paket top up berhasil diperbarui',
            'data' => $package,
        ]);
    }

    public function destroy($id)
    {
        $package = TopupPackage::findOrFail($id);

        if ($package->topupTransactions()->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'Paket tidak dapat dihapus karena sudah digunakan pada transaksi top up.',
            ], 422);
        }

        $package->delete();

        return response()->json([
            'status' => true,
            'message' => 'Paket top up berhasil dihapus',
        ]);
    }

    private function validateStoreData(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:100',
            'coin_amount' => 'required|integer|min:1',
            'bonus_coin' => 'nullable|integer|min:0',
            'price' => 'required|integer|min:1',
            'is_active' => 'nullable|boolean',
        ]);
    }

    private function validateUpdateData(Request $request): array
    {
        return $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'coin_amount' => 'sometimes|required|integer|min:1',
            'bonus_coin' => 'sometimes|nullable|integer|min:0',
            'price' => 'sometimes|required|integer|min:1',
            'is_active' => 'sometimes|boolean',
        ]);
    }
}

