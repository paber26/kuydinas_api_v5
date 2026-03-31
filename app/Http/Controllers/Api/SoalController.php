<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Soal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SoalController extends Controller
{
    private const MAX_EMBEDDED_IMAGE_BYTES = 2097152;
    private const ALLOWED_IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

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
            }
            else {
                $query->doesntHave('tryouts');
            }

        }

        $perPage = $request->input('per_page', 20);
        $soals = $query->paginate($perPage);

        return response()->json([
            'status' => true,
            'data' => $soals
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
            'status' => true,
            'message' => 'Soal berhasil disimpan',
            'data' => $soal
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
            'status' => true,
            'message' => 'Soal berhasil diupdate',
            'data' => $soal
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
            'status' => true,
            'message' => 'Soal berhasil dihapus'
        ]);
    }


    /* ===============================
     VALIDATION
     ================================ */

    private function validateData(Request $request)
    {
        $data = $request->validate([
            'category' => 'required|in:TWK,TIU,TKP',
            'sub_category' => 'nullable|string',
            'difficulty' => 'nullable|string',

            'question' => 'required|string',

            'options' => 'required|array|min:4|max:5',
            'options.*.label' => 'required|string',
            'options.*.text' => 'required|string',
            'options.*.score' => 'nullable|integer|min:1|max:5',

            'correct_answer' => 'nullable|string|in:A,B,C,D,E',
            'explanation' => 'nullable|string',

            'status' => 'nullable|in:aktif,nonaktif',
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

        $data = $this->storeEmbeddedImages($data);

        return $data;
    }

    private function storeEmbeddedImages(array $data): array
    {
        $messages = [];

        $data['question'] = $this->replaceEmbeddedImagesInHtml(
            $data['question'] ?? null,
            $messages,
            'question',
            'Gambar pada pertanyaan'
        );

        $data['explanation'] = $this->replaceEmbeddedImagesInHtml(
            $data['explanation'] ?? null,
            $messages,
            'explanation',
            'Gambar pada pembahasan'
        );

        foreach (($data['options'] ?? []) as $index => $option) {
            $label = $option['label'] ?? chr(65 + $index);

            $data['options'][$index]['text'] = $this->replaceEmbeddedImagesInHtml(
                $option['text'] ?? null,
                $messages,
                "options.$index.text",
                "Gambar pada opsi {$label}"
            );
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }

        return $data;
    }

    private function replaceEmbeddedImagesInHtml(?string $html, array &$messages, string $field, string $label): ?string
    {
        if (!is_string($html) || trim($html) === '') {
            return $html;
        }

        $sources = $this->extractImageSources($html);
        if ($sources === []) {
            return $html;
        }

        $cache = [];

        foreach ($sources as $src) {
            if (!str_starts_with($src, 'data:image/')) {
                continue;
            }

            if (isset($cache[$src])) {
                $html = str_replace($src, $cache[$src], $html);
                continue;
            }

            if (!preg_match('/^data:([^;]+);base64,(.+)$/i', $src, $matches)) {
                $messages[$field] = "{$label} harus memakai format base64 yang valid.";
                return $html;
            }

            $mimeType = strtolower($matches[1]);

            if (!in_array($mimeType, self::ALLOWED_IMAGE_MIME_TYPES, true)) {
                $messages[$field] = "{$label} hanya menerima JPG, PNG, WEBP, atau GIF.";
                return $html;
            }

            if ($this->getDataUriSizeInBytes($src) > self::MAX_EMBEDDED_IMAGE_BYTES) {
                $messages[$field] = "{$label} maksimal 2MB per gambar.";
                return $html;
            }

            $decoded = base64_decode($matches[2], true);
            if ($decoded === false) {
                $messages[$field] = "{$label} harus memakai base64 yang valid.";
                return $html;
            }

            $ext = match ($mimeType) {
                'image/jpeg', 'image/jpg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
                default => 'bin',
            };

            $path = 'soal-images/'.Str::uuid().'.'.$ext;
            Storage::disk('public')->put($path, $decoded, 'public');

            $url = Storage::disk('public')->url($path);
            $cache[$src] = $url;
            $html = str_replace($src, $url, $html);
        }

        return $html;
    }

    private function extractImageSources(?string $html): array
    {
        if (!is_string($html) || trim($html) === '') {
            return [];
        }

        preg_match_all('/<img\b[^>]*src=["\']([^"\']+)["\'][^>]*>/i', $html, $matches);

        return $matches[1] ?? [];
    }

    private function getDataUriSizeInBytes(string $dataUri): int
    {
        $parts = explode(',', $dataUri, 2);
        $payload = preg_replace('/\s+/', '', $parts[1] ?? '');

        $padding = str_ends_with($payload, '==')
            ? 2
            : (str_ends_with($payload, '=') ? 1 : 0);

        return (int) max(0, (strlen($payload) * 3 / 4) - $padding);
    }

}
