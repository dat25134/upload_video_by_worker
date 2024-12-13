let activeUploads = new Map();
let csrfToken = null;

self.onmessage = async function(e) {
    const { action, uploadId, data } = e.data;

    switch (action) {
        case 'INIT':
            csrfToken = data.csrfToken;
            break;

        case 'START_UPLOAD':
            const { file, fileName, chunkSize } = data;
            activeUploads.set(uploadId, { cancelled: false });
            await handleFileUpload(uploadId, file, fileName, chunkSize);
            break;

        case 'CANCEL_UPLOAD':
            if (activeUploads.has(uploadId)) {
                activeUploads.get(uploadId).cancelled = true;
                activeUploads.delete(uploadId);
            }
            break;
    }
};

async function handleFileUpload(uploadId, file, fileName, chunkSize) {
    const chunks = Math.ceil(file.size / chunkSize);
    const uploadInfo = activeUploads.get(uploadId);

    for (let i = 0; i < chunks; i++) {
        if (uploadInfo.cancelled) {
            self.postMessage({
                type: 'UPLOAD_CANCELLED',
                uploadId
            });
            return;
        }

        const chunk = file.slice(
            i * chunkSize,
            Math.min((i + 1) * chunkSize, file.size)
        );

        const formData = new FormData();
        formData.append('file', chunk);
        formData.append('fileName', fileName);
        formData.append('chunkNumber', i);
        formData.append('totalChunks', chunks);
        formData.append('chunk', true);
        formData.append('uploadId', uploadId);

        try {
            const response = await fetch('/upload', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error(`Upload failed: ${response.statusText}`);
            }

            const progress = Math.round(((i + 1) / chunks) * 100);
            self.postMessage({
                type: 'PROGRESS',
                uploadId,
                progress,
                fileName
            });

        } catch (error) {
            self.postMessage({
                type: 'ERROR',
                uploadId,
                error: error.message
            });
            return;
        }
    }

    self.postMessage({
        type: 'COMPLETE',
        uploadId,
        fileName
    });

    activeUploads.delete(uploadId);
} 