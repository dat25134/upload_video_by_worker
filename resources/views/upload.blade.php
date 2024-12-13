@extends('layouts.app')

@section('content')
<input type="file" id="fileUpload">

<script>
    const fileInput = document.getElementById('fileUpload');
    const CHUNK_SIZE = 1024 * 1024; // 1MB chunks - có thể điều chỉnh kích thước ở đây

    fileInput.addEventListener('change', async function(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        // Start upload using UploadManager with custom chunk size
        window.UploadManager.startUpload(file, {
            chunkSize: CHUNK_SIZE,
            // Có thể thêm các options khác ở đây
            maxRetries: 3,
            timeout: 30000, // 30 seconds
            // validateFile: (file) => file.size < 1024 * 1024 * 1024, // 1GB limit
        });
    });

    // Tùy chọn: Thêm các validation
    function validateFile(file) {
        const maxSize = 1024 * 1024 * 1024; // 1GB
        const allowedTypes = ['video/mp4', 'video/quicktime', 'video/x-msvideo'];

        if (file.size > maxSize) {
            alert('File too large. Maximum size is 1GB');
            return false;
        }

        if (!allowedTypes.includes(file.type)) {
            alert('Invalid file type. Only video files are allowed');
            return false;
        }

        return true;
    }
</script>
@endsection 