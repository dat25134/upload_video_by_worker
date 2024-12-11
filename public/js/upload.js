import ChunkedUploader from '/js/chunked-uploader.js';

function startUpload() {
    const fileInput = document.getElementById('file-input');
    const file = fileInput.files[0];
    if (!file) return;

    const progressBar = document.getElementById('progress');
    const statusElement = document.getElementById('upload-status');

    const uploader = new ChunkedUploader(file, {
        onProgress: (progress) => {
            progressBar.style.width = `${progress * 100}%`;
            statusElement.textContent = `Uploading: ${Math.round(progress * 100)}%`;
        },
        onComplete: () => {
            statusElement.textContent = 'Upload completed!';
        },
        onError: (error) => {
            statusElement.textContent = `Error: ${error.message}`;
        }
    });

    uploader.start();
}

export default startUpload;
