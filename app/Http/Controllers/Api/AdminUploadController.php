<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminUploadController extends Controller
{
    public function storeImage(Request $request)
    {
        $validated = $request->validate([
            // CKEditor SimpleUploadAdapter uses "upload" by default.
            'upload' => 'required|file|mimes:jpg,jpeg,png,webp,gif|max:2048',
        ]);

        $file = $validated['upload'];
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $path = 'soal-images/'.Str::uuid().'.'.$ext;

        Storage::disk('public')->putFileAs('soal-images', $file, basename($path), [
            'visibility' => 'public',
        ]);

        $url = Storage::disk('public')->url($path);

        // CKEditor expects { default: "url" }.
        return response()->json([
            'default' => $url,
            'url' => $url,
        ], 201);
    }
}

