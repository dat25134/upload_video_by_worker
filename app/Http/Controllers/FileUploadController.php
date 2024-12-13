<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileUploadController extends Controller
{
    public function index()
    {
        return view('upload');
    }

    public function upload(Request $request)
    {
        if ($request->has('chunk')) {
            // Xử lý từng chunk
            $chunk = $request->file('file');
            $fileName = $request->input('fileName');
            $chunkNumber = $request->input('chunkNumber');
            $totalChunks = $request->input('totalChunks');

            // Lưu chunk vào thư mục tạm
            $chunkPath = storage_path('app/chunks/' . $fileName . '.part' . $chunkNumber);
            file_put_contents($chunkPath, file_get_contents($chunk));

            // Kiểm tra nếu đã nhận đủ chunks
            if ($this->allChunksUploaded($fileName, $totalChunks)) {
                // Ghép các chunks thành file hoàn chỉnh
                $this->mergeChunks($fileName, $totalChunks);
                
                return response()->json([
                    'message' => 'File uploaded successfully'
                ]);
            }

            return response()->json([
                'message' => 'Chunk uploaded successfully'
            ]);
        }

        return response()->json([
            'message' => 'No chunk found'
        ], 400);
    }

    private function allChunksUploaded($fileName, $totalChunks)
    {
        for ($i = 0; $i < $totalChunks; $i++) {
            if (!file_exists(storage_path('app/chunks/' . $fileName . '.part' . $i))) {
                return false;
            }
        }
        return true;
    }

    private function mergeChunks($fileName, $totalChunks)
    {
        $finalPath = storage_path('app/uploads/' . $fileName);
        $chunks = [];

        // Ghép các chunks
        for ($i = 0; $i < $totalChunks; $i++) {
            $chunks[] = storage_path('app/chunks/' . $fileName . '.part' . $i);
        }

        // Tạo file cuối cùng
        $final = fopen($finalPath, 'wb');

        foreach ($chunks as $chunk) {
            fwrite($final, file_get_contents($chunk));
            unlink($chunk); // Xóa chunk sau khi đã ghép
        }

        fclose($final);
    }
} 