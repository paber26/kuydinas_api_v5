<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TopupTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdminTopupTransactionController extends Controller
{
    public function summary(Request $request)
    {
        $status = (string) ($request->input('status') ?? 'paid');
        $from = $request->input('from');
        $to = $request->input('to');

        $query = TopupTransaction::query();

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($from) {
            $query->where('created_at', '>=', Carbon::parse($from)->startOfDay());
        }

        if ($to) {
            $query->where('created_at', '<=', Carbon::parse($to)->endOfDay());
        }

        $total = (int) $query->sum('gross_amount');

        return response()->json([
            'status' => true,
            'data' => [
                'total_gross_amount' => $total,
            ],
        ]);
    }
}

