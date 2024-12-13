@extends('layouts.app')

@section('content')
<input type="file" id="fileUpload">

<script>
    const CHUNK_SIZE = 1024 * 1024; // 1MB chunks
    const fileInput = document.getElementById('fileUpload');

    fileInput.addEventListener('change', async function(e) {
        const file = e.target.files[0];
        const chunks = Math.ceil(file.size / CHUNK_SIZE);
        
        // Show toast with filename
        window.ToastUpload.show(file.name);
        
        for (let i = 0; i < chunks; i++) {
            const chunk = file.slice(
                i * CHUNK_SIZE,
                Math.min((i + 1) * CHUNK_SIZE, file.size)
            );
            
            const formData = new FormData();
            formData.append('file', chunk);
            formData.append('fileName', file.name);
            formData.append('chunkNumber', i);
            formData.append('totalChunks', chunks);
            formData.append('chunk', true);

            try {
                await fetch('/upload', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                // Update progress
                const progress = Math.round(((i + 1) / chunks) * 100);
                window.ToastUpload.updateProgress(progress);
            } catch (error) {
                console.error('Upload failed:', error);
                alert('Upload failed. Please try again.');
                window.ToastUpload.hide();
            }
        }
    });
</script>
@endsection 