<!DOCTYPE html>
<html>
<head>
    <title>Chunk File Upload</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        .toast-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }

        .toast {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 300px;
            display: none;
        }

        .progress-bar-container {
            background-color: #f0f0f0;
            border-radius: 4px;
            height: 8px;
            margin-top: 10px;
            overflow: hidden;
        }

        .progress-bar {
            background-color: #4CAF50;
            height: 100%;
            width: 0%;
            transition: width 0.3s ease;
        }

        .toast-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .toast-title {
            font-weight: bold;
            margin: 0;
        }

        .toast-body {
            color: #666;
        }

        .progress-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
            text-align: right;
        }
    </style>
</head>
<body>
    <input type="file" id="fileUpload">

    <div class="toast-container">
        <div class="toast" id="uploadToast">
            <div class="toast-header">
                <span class="toast-title">Uploading File</span>
                <span id="fileName"></span>
            </div>
            <div class="toast-body">
                <div class="progress-bar-container">
                    <div class="progress-bar" id="progressBar"></div>
                </div>
                <div class="progress-text" id="progressText">0%</div>
            </div>
        </div>
    </div>

    <script>
        const CHUNK_SIZE = 1024 * 1024; // 1MB chunks
        const fileInput = document.getElementById('fileUpload');
        const progressBar = document.getElementById('progressBar');
        const progressText = document.getElementById('progressText');
        const uploadToast = document.getElementById('uploadToast');
        const fileNameElement = document.getElementById('fileName');

        fileInput.addEventListener('change', async function(e) {
            const file = e.target.files[0];
            const chunks = Math.ceil(file.size / CHUNK_SIZE);
            
            // Show toast and set filename
            uploadToast.style.display = 'block';
            fileNameElement.textContent = file.name;
            
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
                    progressBar.style.width = `${progress}%`;
                    progressText.textContent = `${progress}%`;

                    // If upload is complete
                    if (progress === 100) {
                        setTimeout(() => {
                            uploadToast.style.display = 'none';
                            progressBar.style.width = '0%';
                            progressText.textContent = '0%';
                        }, 2000);
                    }
                } catch (error) {
                    console.error('Upload failed:', error);
                    alert('Upload failed. Please try again.');
                    uploadToast.style.display = 'none';
                }
            }
        });
    </script>
</body>
</html> 