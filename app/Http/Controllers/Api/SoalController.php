<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Soal;
use Illuminate\Http\Request;

class SoalController extends Controller
{

/* ===============================
   GET /api/soal
================================ */

public function index(Request $request)
{
    $query = Soal::withCount('tryouts')->latest();

    // Filter status
    if ($request->filled('status')) {
        $query->where('status', $request->status);
    }

    // Filter kategori
    if ($request->filled('category')) {
        $query->where('category', $request->category);
    }

    // Filter soal yang sudah dipakai
    if ($request->filled('used')) {

        if ($request->boolean('used')) {
            $query->has('tryouts');
        } else {
            $query->doesntHave('tryouts');
        }

    }

    $soals = $query->paginate(20);

    return response()->json([
        'status' => true,
        'data'   => $soals
    ]);
}


/* ===============================
   GET /api/soal/{id}
================================ */

public function show($id)
{
    $soal = Soal::findOrFail($id);

    return response()->json([
        'status' => true,
        'data' => $soal
    ]);
}


/* ===============================
   POST /api/soal
================================ */

public function store(Request $request)
{
    $data = $this->validateData($request);

    // default status
    $data['status'] = $data['status'] ?? 'nonaktif';

    $soal = Soal::create($data);

    return response()->json([
        'status'  => true,
        'message' => 'Soal berhasil disimpan',
        'data'    => $soal
    ], 201);
}


/* ===============================
   PUT /api/soal/{id}
================================ */

public function update(Request $request, $id)
{
    $soal = Soal::findOrFail($id);

    $data = $this->validateData($request);

    $soal->update($data);

    return response()->json([
        'status'  => true,
        'message' => 'Soal berhasil diupdate',
        'data'    => $soal
    ]);
}


/* ===============================
   DELETE /api/soal/{id}
================================ */

public function destroy($id)
{
    $soal = Soal::findOrFail($id);

    // jangan hapus jika sudah dipakai tryout
    if ($soal->tryouts()->exists()) {

        return response()->json([
            'status' => false,
            'message' => 'Soal tidak dapat dihapus karena sudah digunakan dalam tryout'
        ], 422);

    }

    $soal->delete();

    return response()->json([
        'status'  => true,
        'message' => 'Soal berhasil dihapus'
    ]);
}


/* ===============================
   VALIDATION
================================ */

private function validateData(Request $request)
{
    $data = $request->validate([
        'category'        => 'required|in:TWK,TIU,TKP',
        'sub_category'    => 'nullable|string',
        'difficulty'      => 'nullable|string',

        'question'        => 'required|string',

        'options'         => 'required|array|min:4|max:5',
        'options.*.label' => 'required|string',
        'options.*.text'  => 'required|string',
        'options.*.score' => 'nullable|integer|min:1|max:5',

        'correct_answer'  => 'nullable|string|in:A,B,C,D,E',
        'explanation'     => 'nullable|string',

        'status'          => 'nullable|in:aktif,nonaktif',
    ]);


    /* =============================
       TKP RULE
    ============================= */

    if ($data['category'] === 'TKP') {

        foreach ($data['options'] as $option) {

            if (!isset($option['score'])) {
                abort(422, 'Setiap opsi TKP wajib memiliki skor');
            }

        }

        $data['correct_answer'] = null;

    }


    /* =============================
       TWK / TIU RULE
    ============================= */

    if ($data['category'] !== 'TKP') {

        if (empty($data['correct_answer'])) {
            abort(422, 'Jawaban benar wajib dipilih untuk TWK/TIU');
        }

    }

    return $data;
}

}