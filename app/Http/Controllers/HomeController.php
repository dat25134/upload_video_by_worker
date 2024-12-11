<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class HomeController extends Controller
{
    public function upload()
    {
        return view('upload');
    }

    public function initUpload(Request $request)
    {
        $sessionId = Str::uuid();

        Cache::put("upload_session_{$sessionId}", [
            'filename' => $request->filename,
            'total_chunks' => $request->totalChunks,
            'uploaded_chunks' => []
        ], 24 * 3600);

        return response()->json(['sessionId' => $sessionId]);
    }

    public function uploadChunk(Request $request)
    {
        $chunk = $request->file('chunk');
        $sessionId = $request->sessionId;
        $chunkIndex = $request->chunkIndex;

        // Lưu chunk vào temporary storage
        Storage::disk('public')->putFileAs(
            "chunks/{$sessionId}",
            $chunk,
            "chunk_{$chunkIndex}"
        );

        // Cập nhật session
        $session = Cache::get("upload_session_{$sessionId}");
        $session['uploaded_chunks'][] = $chunkIndex;
        Cache::put("upload_session_{$sessionId}", $session, 24 * 3600);

        return response()->json(['success' => true]);
    }

    public function finalizeUpload(Request $request)
    {
        $sessionId = $request->sessionId;
        $session = Cache::get("upload_session_{$sessionId}");

        try {
            // Đảm bảo các thư mục tồn tại
            Storage::disk('public')->makeDirectory('uploads', 0755, true);

            $uploadDir = dirname(Storage::disk('public')->path("uploads/{$sessionId}/{$session['filename']}"));
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Tạo file gốc trước
            $outputPath = Storage::disk('public')->path("uploads/{$sessionId}/{$session['filename']}");
            touch($outputPath);
            $outputFile = fopen($outputPath, 'wb');

            // Merge các chunks
            for ($i = 0; $i < $session['total_chunks']; $i++) {
                $chunkPath = Storage::disk('public')->path("chunks/{$sessionId}/chunk_{$i}");
                $chunkContent = file_get_contents($chunkPath);
                fwrite($outputFile, $chunkContent);
            }
            fclose($outputFile);

            // Cleanup
            Storage::disk('public')->deleteDirectory("chunks/{$sessionId}");
            Cache::forget("upload_session_{$sessionId}");

            return response()->json([
                'success' => true,
                'original' => "/uploads/{$sessionId}/{$session['filename']}"
            ]);

        } catch (\Exception $e) {
            // Cleanup khi có lỗi
            Storage::disk('public')->deleteDirectory("chunks/{$sessionId}");
            Storage::disk('public')->deleteDirectory("uploads/{$sessionId}");
            Cache::forget("upload_session_{$sessionId}");

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
