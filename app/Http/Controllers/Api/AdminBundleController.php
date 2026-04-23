<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bundle;
use App\Models\BundleTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminBundleController extends Controller
{
    /**
     * Daftar semua bundle.
     */
    public function index(Request $request)
    {
        $query = Bundle::withCount('tryouts')
            ->withCount(['transactions as paid_count' => function ($q) {
                $q->where('status', 'paid');
            }])
            ->latest();

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $bundles = $query->get()->map(function ($bundle) {
            return [
                'id' => $bundle->id,
                'name' => $bundle->name,
                'description' => $bundle->description,
                'price' => (int) $bundle->price,
                'cover_image' => $bundle->cover_image,
                'limit_type' => $bundle->limit_type,
                'limit_quota' => $bundle->limit_quota,
                'limit_start_date' => optional($bundle->limit_start_date)->format('Y-m-d'),
                'limit_end_date' => optional($bundle->limit_end_date)->format('Y-m-d'),
                'is_active' => $bundle->is_active,
                'is_available' => $bundle->isAvailable(),
                'tryouts_count' => $bundle->tryouts_count,
                'paid_count' => $bundle->paid_count,
                'created_at' => optional($bundle->created_at)->toDateTimeString(),
            ];
        });

        return response()->json([
            'status' => true,
            'data' => $bundles,
        ]);
    }

    /**
     * Buat bundle baru.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'description' => 'nullable|string',
            'price' => 'required|integer|min:1',
            'cover_image' => 'nullable|string',
            'limit_type' => 'required|in:time,quota',
            'limit_quota' => 'nullable|integer|min:1',
            'limit_start_date' => 'nullable|date',
            'limit_end_date' => 'nullable|date|after_or_equal:limit_start_date',
            'is_active' => 'boolean',
            'tryout_ids' => 'required|array|min:1',
            'tryout_ids.*' => 'integer|exists:tryouts,id',
        ]);

        $bundle = DB::transaction(function () use ($validated) {
            $bundle = Bundle::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'price' => $validated['price'],
                'cover_image' => $validated['cover_image'] ?? null,
                'limit_type' => $validated['limit_type'],
                'limit_quota' => $validated['limit_quota'] ?? null,
                'limit_start_date' => $validated['limit_start_date'] ?? null,
                'limit_end_date' => $validated['limit_end_date'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            $bundle->tryouts()->sync($validated['tryout_ids']);

            return $bundle;
        });

        $bundle->load('tryouts');

        return response()->json([
            'status' => true,
            'message' => 'Bundle berhasil dibuat.',
            'data' => $this->formatBundle($bundle),
        ], 201);
    }

    /**
     * Detail bundle.
     */
    public function show($id)
    {
        $bundle = Bundle::with('tryouts')
            ->withCount(['transactions as paid_count' => function ($q) {
                $q->where('status', 'paid');
            }])
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $this->formatBundle($bundle),
        ]);
    }

    /**
     * Update bundle.
     */
    public function update(Request $request, $id)
    {
        $bundle = Bundle::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:150',
            'description' => 'nullable|string',
            'price' => 'sometimes|required|integer|min:1',
            'cover_image' => 'nullable|string',
            'limit_type' => 'sometimes|required|in:time,quota',
            'limit_quota' => 'nullable|integer|min:1',
            'limit_start_date' => 'nullable|date',
            'limit_end_date' => 'nullable|date|after_or_equal:limit_start_date',
            'is_active' => 'boolean',
            'tryout_ids' => 'sometimes|required|array|min:1',
            'tryout_ids.*' => 'integer|exists:tryouts,id',
        ]);

        DB::transaction(function () use ($bundle, $validated) {
            $bundle->update(collect($validated)->except('tryout_ids')->toArray());

            if (isset($validated['tryout_ids'])) {
                $bundle->tryouts()->sync($validated['tryout_ids']);
            }
        });

        $bundle->load('tryouts');

        return response()->json([
            'status' => true,
            'message' => 'Bundle berhasil diperbarui.',
            'data' => $this->formatBundle($bundle),
        ]);
    }

    /**
     * Hapus bundle.
     */
    public function destroy($id)
    {
        $bundle = Bundle::findOrFail($id);

        $hasPaidTransactions = $bundle->transactions()->where('status', 'paid')->exists();

        if ($hasPaidTransactions) {
            return response()->json([
                'status' => false,
                'message' => 'Bundle tidak bisa dihapus karena sudah ada transaksi yang terbayar. Nonaktifkan saja.',
            ], 422);
        }

        $bundle->delete();

        return response()->json([
            'status' => true,
            'message' => 'Bundle berhasil dihapus.',
        ]);
    }

    /**
     * Daftar transaksi pembeli dari bundle tertentu.
     */
    public function transactions($id)
    {
        $bundle = Bundle::findOrFail($id);

        $transactions = BundleTransaction::where('bundle_id', $bundle->id)
            ->with('user')
            ->latest()
            ->get()
            ->map(function ($trx) {
                return [
                    'id' => $trx->id,
                    'user' => [
                        'id' => $trx->user?->id,
                        'name' => $trx->user?->name,
                        'email' => $trx->user?->email,
                        'whatsapp' => $trx->user?->whatsapp,
                    ],
                    'order_id' => $trx->order_id,
                    'gross_amount' => (int) $trx->gross_amount,
                    'status' => $trx->status,
                    'payment_type' => $trx->payment_type,
                    'paid_at' => optional($trx->paid_at)->toDateTimeString(),
                    'created_at' => optional($trx->created_at)->toDateTimeString(),
                ];
            });

        return response()->json([
            'status' => true,
            'data' => $transactions,
        ]);
    }

    private function formatBundle(Bundle $bundle): array
    {
        return [
            'id' => $bundle->id,
            'name' => $bundle->name,
            'description' => $bundle->description,
            'price' => (int) $bundle->price,
            'cover_image' => $bundle->cover_image,
            'limit_type' => $bundle->limit_type,
            'limit_quota' => $bundle->limit_quota,
            'limit_start_date' => optional($bundle->limit_start_date)->format('Y-m-d'),
            'limit_end_date' => optional($bundle->limit_end_date)->format('Y-m-d'),
            'is_active' => $bundle->is_active,
            'is_available' => $bundle->isAvailable(),
            'paid_count' => $bundle->paid_count ?? $bundle->purchasedCount(),
            'tryouts' => $bundle->tryouts->map(function ($tryout) {
                return [
                    'id' => $tryout->id,
                    'title' => $tryout->title,
                    'duration' => (int) $tryout->duration,
                    'type' => $tryout->type,
                    'question_count' => (int) ($tryout->twk_target ?? 0)
                        + (int) ($tryout->tiu_target ?? 0)
                        + (int) ($tryout->tkp_target ?? 0),
                ];
            }),
            'created_at' => optional($bundle->created_at)->toDateTimeString(),
        ];
    }
}
