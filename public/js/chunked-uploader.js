class ChunkedUploader {
    constructor(file, options = {}) {
        this.file = file;
        this.chunkSize = options.chunkSize || 2 * 1024 * 1024; // 2MB chunks
        this.maxParallelUploads = options.maxParallelUploads || 3;
        this.retryAttempts = options.retryAttempts || 3;
        this.onProgress = options.onProgress || (() => {});
        this.onComplete = options.onComplete || (() => {});
        this.onError = options.onError || (() => {});

        this.chunks = this.createChunks();
        this.uploadedChunks = new Set();
        this.isUploading = false;
        this.workers = [];
        this.csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    }

    createChunks() {
        const chunks = [];
        let start = 0;
        while (start < this.file.size) {
            chunks.push({
                start,
                end: Math.min(start + this.chunkSize, this.file.size)
            });
            start += this.chunkSize;
        }
        return chunks;
    }

    async start() {
        if (this.isUploading) return;
        this.isUploading = true;
        try {
            const response = await fetch('/upload/init', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken
                },
                body: JSON.stringify({
                    filename: this.file.name,
                    totalChunks: this.chunks.length,
                    fileSize: this.file.size,
                    mimeType: this.file.type
                })
            });

            if (!response.ok) {
                throw new Error('Failed to initialize upload session');
            }

            const { sessionId } = await response.json();
            await this.uploadChunks(sessionId);
            await this.finalizeUpload(sessionId);
            this.onComplete();
        } catch (error) {
            console.error(error);
            this.onError(error);
        } finally {
            this.cleanup();
            this.isUploading = false;
        }
    }

    async uploadChunks(sessionId) {
        const chunksToUpload = this.chunks.filter((_, index) =>
            !this.uploadedChunks.has(index)
        );

        while (chunksToUpload.length > 0) {
            const uploadPromises = [];
            const currentChunks = chunksToUpload.splice(0, this.maxParallelUploads);

            for (const chunk of currentChunks) {
                uploadPromises.push(this.uploadChunk(chunk, sessionId));
            }

            await Promise.all(uploadPromises);

            // Cập nhật progress
            const progress = this.uploadedChunks.size / this.chunks.length;
            this.onProgress(progress);
        }
    }

    async uploadChunk(chunk, sessionId) {
        return new Promise((resolve, reject) => {
            const worker = new Worker('/js/upload-worker.js');
            this.workers.push(worker);

            let attempts = 0;
            const maxAttempts = this.retryAttempts;

            const attemptUpload = () => {
                worker.postMessage({
                    chunk,
                    chunkIndex: this.chunks.indexOf(chunk),
                    sessionId,
                    file: this.file,
                    csrfToken: this.csrfToken
                });
            };

            worker.onmessage = (e) => {
                const { success, chunkIndex, error } = e.data;

                if (success) {
                    this.uploadedChunks.add(chunkIndex);
                    worker.terminate();
                    resolve();
                } else {
                    attempts++;
                    if (attempts >= maxAttempts) {
                        worker.terminate();
                        reject(new Error(error));
                    } else {
                        setTimeout(() => attemptUpload(), 1000 * attempts);
                    }
                }
            };

            worker.onerror = (error) => {
                worker.terminate();
                reject(error);
            };

            attemptUpload();
        });
    }

    async finalizeUpload(sessionId) {
        const response = await fetch('/upload/finalize', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ sessionId })
        });

        if (!response.ok) throw new Error('Failed to finalize upload');
    }

    // Thêm phương thức để dọn dẹp workers
    cleanup() {
        this.workers.forEach(worker => worker.terminate());
        this.workers = [];
    }
    // ... copy all the class methods ...
}

export default ChunkedUploader;
