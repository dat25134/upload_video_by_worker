<!DOCTYPE html>
<html>
<head>
    <title>Optimized File Upload</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        .progress-container {
            width: 100%;
            margin: 20px 0;
        }
        .progress-bar {
            height: 20px;
            background-color: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
        }
        .progress {
            height: 100%;
            background-color: #4CAF50;
            width: 0%;
            transition: width 0.5s ease-in-out;
        }
        .upload-status {
            margin-top: 10px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="upload-container">
        <input type="file" id="file-input" />
        <button id="submit-upload">Upload</button>

        <div class="progress-container">
            <div class="progress-bar">
                <div id="progress" class="progress"></div>
            </div>
            <div id="upload-status" class="upload-status"></div>
        </div>
    </div>

    <script type="module">
        import startUpload from '/js/upload.js';

        document.getElementById('submit-upload').addEventListener('click', startUpload);
    </script>
</body>
</html>
