<div class="toast-container" id="toastContainer"></div>

<template id="toastTemplate">
    <div class="toast">
        <div class="toast-header">
            <span class="toast-title">Uploading File</span>
            <span class="fileName"></span>
            <button class="cancel-btn">✕</button>
        </div>
        <div class="toast-body">
            <div class="progress-bar-container">
                <div class="progress-bar"></div>
            </div>
            <div class="progress-text">0%</div>
        </div>
    </div>
</template>

<style>
    .toast-container {
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 1000;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .toast {
        background: white;
        border-radius: 8px;
        padding: 15px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        width: 300px;
        opacity: 1;
        transition: opacity 0.3s ease;
    }

    .progress-bar-container {
        background-color: #f0f0f0;
        border-radius: 4px;
        height: 6px;
        margin: 10px 0;
        overflow: hidden;
    }

    .progress-bar {
        background-color: #4CAF50;
        height: 100%;
        width: 0;
        transition: width 0.3s ease;
        border-radius: 4px;
    }

    .toast-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }

    .toast-title {
        font-weight: bold;
        color: #333;
    }

    .fileName {
        font-size: 0.9em;
        color: #666;
        margin: 0 10px;
        flex-grow: 1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .progress-text {
        font-size: 12px;
        color: #666;
        text-align: right;
    }

    .cancel-btn {
        background: none;
        border: none;
        color: #999;
        cursor: pointer;
        padding: 0 5px;
        font-size: 16px;
        transition: color 0.2s ease;
    }

    .cancel-btn:hover {
        color: #ff4444;
    }

    /* Animation for progress updates */
    @keyframes progress-pulse {
        0% { opacity: 1; }
        50% { opacity: 0.7; }
        100% { opacity: 1; }
    }

    .progress-updating {
        animation: progress-pulse 1s ease infinite;
    }
</style>

<script>
    window.UploadManager = {
        serviceWorker: null,
        activeUploads: new Map(),
        uploadChannel: null,

        async init() {
            try {
                // Khởi tạo BroadcastChannel
                this.uploadChannel = new BroadcastChannel('upload-channel');
                this.uploadChannel.onmessage = this.handleChannelMessage.bind(this);

                // Khởi tạo IndexedDB
                await this.initializeDB();

                if ('serviceWorker' in navigator) {
                    // Đăng ký service worker
                    const registration = await navigator.serviceWorker.register('/js/upload-service-worker.js');
                    this.serviceWorker = registration.active || registration.waiting || registration.installing;

                    // Đợi service worker active
                    if (this.serviceWorker.state !== 'activated') {
                        await new Promise(resolve => {
                            this.serviceWorker.addEventListener('statechange', e => {
                                if (e.target.state === 'activated') resolve();
                            });
                        });
                    }

                    // Lắng nghe messages từ service worker
                    navigator.serviceWorker.addEventListener('message', this.handleWorkerMessage.bind(this));

                    // Khởi tạo CSRF token
                    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
                    this.serviceWorker.postMessage({
                        action: 'INIT',
                        data: { csrfToken }
                    });

                    // Yêu cầu danh sách active uploads
                    this.requestActiveUploads();
                }

                // Cleanup old uploads on init
                await this.cleanupOldUploads();

                // Schedule periodic cleanup
                setInterval(() => this.cleanupOldUploads(), 5 * 60 * 1000); // Every 5 minutes

                console.log('Upload Manager initialized successfully');
            } catch (error) {
                console.error('Initialization failed:', error);
                this.showCriticalError('Failed to initialize upload system');
            }
        },

        async requestActiveUploads() {
            if (this.serviceWorker) {
                this.serviceWorker.postMessage({
                    action: 'GET_ACTIVE_UPLOADS'
                });
            }
        },

        handleChannelMessage(event) {
            this.handleMessage(event.data);
        },

        handleWorkerMessage(event) {
            this.handleMessage(event.data);
        },

        async handleMessage(data) {
            const { type, uploadId, progress, fileName, error, uploads } = data;

            switch (type) {
                case 'ACTIVE_UPLOADS':
                    // Xóa toasts cũ trước khi thêm mới
                    this.clearAllToasts();
                    
                    // Chỉ hiển thị uploads thực sự đang active
                    uploads.forEach(upload => {
                        if (upload.status === 'in_progress' && upload.progress < 100) {
                            this.createToast(upload.uploadId, upload.fileName);
                            this.updateProgress(upload.uploadId, upload.progress || 0);
                            this.activeUploads.set(upload.uploadId, upload);
                        } else {
                            // Xóa uploads đã hoàn thành khỏi IndexedDB
                            this.cleanupUpload(upload.uploadId);
                        }
                    });
                    break;

                case 'PROGRESS':
                    if (!this.activeUploads.has(uploadId)) {
                        this.createToast(uploadId, fileName);
                        this.activeUploads.set(uploadId, { fileName, progress });
                    }
                    this.updateProgress(uploadId, progress);
                    break;

                case 'COMPLETE':
                    this.updateProgress(uploadId, 100);
                    const toast = document.querySelector(`.toast[data-upload-id="${uploadId}"]`);
                    if (toast) {
                        toast.querySelector('.toast-title').textContent = 'Upload Complete';
                        setTimeout(() => {
                            toast.style.opacity = '0';
                            setTimeout(() => toast.remove(), 300);
                        }, 2000);
                    }
                    this.activeUploads.delete(uploadId);
                    break;

                case 'ERROR':
                    this.showError(uploadId, error);
                    this.activeUploads.delete(uploadId);
                    break;

                // ... other cases ...
            }
        },

        async initializeDB() {
            return new Promise((resolve, reject) => {
                const request = indexedDB.open('UploadDB', 1);
                
                request.onerror = () => reject(request.error);
                
                request.onsuccess = () => {
                    this.db = request.result;
                    resolve();
                };
                
                request.onupgradeneeded = (event) => {
                    const db = event.target.result;
                    if (!db.objectStoreNames.contains('uploads')) {
                        const store = db.createObjectStore('uploads', { keyPath: 'uploadId' });
                        store.createIndex('status', 'status');
                        store.createIndex('lastUpdated', 'lastUpdated');
                    }
                };
            });
        },

        async openDB() {
            if (this.db) return this.db;
            await this.initializeDB();
            return this.db;
        },

        createToast(uploadId, fileName) {
            const template = document.getElementById('toastTemplate');
            const toast = template.content.cloneNode(true).querySelector('.toast');
            
            toast.dataset.uploadId = uploadId;
            toast.querySelector('.fileName').textContent = fileName;
            toast.querySelector('.cancel-btn').onclick = () => this.cancelUpload(uploadId);
            
            document.getElementById('toastContainer').appendChild(toast);
            
            // Force reflow to ensure animation works
            toast.offsetHeight;
            
            return toast;
        },

        startUpload(file) {
            this.init();
            const uploadId = Date.now().toString();
            
            // Create and show toast
            const toast = this.createToast(uploadId, file.name);
            
            // Store upload info
            this.activeUploads.set(uploadId, {
                fileName: file.name,
                toast: toast
            });

            // Start upload in worker
            this.serviceWorker.postMessage({
                action: 'START_UPLOAD',
                uploadId,
                data: {
                    file,
                    fileName: file.name,
                    chunkSize: 1024 * 1024 // 1MB
                }
            });

            return uploadId;
        },

        cancelUpload(uploadId) {
            this.serviceWorker.postMessage({
                action: 'CANCEL_UPLOAD',
                uploadId
            });
        },

        updateProgress(uploadId, progress) {
            const toast = document.querySelector(`.toast[data-upload-id="${uploadId}"]`);
            if (toast) {
                const progressBar = toast.querySelector('.progress-bar');
                const progressText = toast.querySelector('.progress-text');
                
                // Add animation class
                progressBar.classList.add('progress-updating');
                
                // Update progress
                progressBar.style.width = `${progress}%`;
                progressText.textContent = `${progress}%`;
                
                // Remove animation class after transition
                setTimeout(() => {
                    progressBar.classList.remove('progress-updating');
                }, 300);
            }
        },

        showCriticalError(error) {
            const errorToast = document.createElement('div');
            errorToast.className = 'toast critical-error';
            errorToast.innerHTML = `
                <div class="toast-header">
                    <span class="toast-title">Critical Error</span>
                </div>
                <div class="toast-body">
                    <div class="error-message">${error}</div>
                    <div class="error-hint">All uploads have been terminated. Please refresh the page.</div>
                </div>
            `;
            document.getElementById('toastContainer').appendChild(errorToast);
        },

        async unregisterServiceWorker() {
            if ('serviceWorker' in navigator) {
                const registrations = await navigator.serviceWorker.getRegistrations();
                for (const registration of registrations) {
                    await registration.unregister();
                }
                this.serviceWorker = null;
                this.activeUploads.clear();
            }
        },

        async cancelUpload(uploadId) {
            if (!this.serviceWorker) return;

            // Gửi lệnh hủy tới service worker
            this.serviceWorker.postMessage({
                action: 'CANCEL_UPLOAD',
                uploadId
            });

            // Xóa khỏi IndexedDB
            const db = await this.openDB();
            const tx = db.transaction('uploads', 'readwrite');
            const store = tx.objectStore('uploads');
            await store.delete(uploadId);

            // Xóa toast
            const toast = document.querySelector(`.toast[data-upload-id="${uploadId}"]`);
            if (toast) {
                toast.remove();
            }

            this.activeUploads.delete(uploadId);
        },

        // Hủy tất cả uploads đang chạy
        async cancelAllUploads() {
            const uploadIds = Array.from(this.activeUploads.keys());
            for (const uploadId of uploadIds) {
                await this.cancelUpload(uploadId);
            }
        },

        clearAllToasts() {
            const toastContainer = document.getElementById('toastContainer');
            toastContainer.innerHTML = '';
            this.activeUploads.clear();
        },

        async cleanupUpload(uploadId) {
            try {
                const db = await this.openDB();
                const tx = db.transaction('uploads', 'readwrite');
                const store = tx.objectStore('uploads');
                await store.delete(uploadId);
            } catch (error) {
                console.error('Error cleaning up upload:', error);
            }
        },

        // Thêm cleanup định kỳ
        async cleanupOldUploads() {
            try {
                const db = await this.openDB();
                const tx = db.transaction('uploads', 'readwrite');
                const store = tx.objectStore('uploads');
                const uploads = await store.getAll();
                
                const oneHourAgo = Date.now() - (60 * 60 * 1000);
                console.log(uploads);
                
                if (uploads && uploads.length > 0) {
                    for (const upload of uploads) {
                        if (!upload) continue;
                        
                        // Xóa uploads cũ hơn 1 giờ hoặc đã hoàn thành
                        if (upload.lastUpdated < oneHourAgo || 
                            upload.status === 'completed' || 
                            upload.status === 'error' ||
                            upload.progress === 100) {
                            await store.delete(upload.uploadId);
                        }
                    }
                }
            } catch (error) {
                console.error('Error cleaning up old uploads:', error);
            }
        }
    };

    // Khởi tạo khi page load
    document.addEventListener('DOMContentLoaded', () => {
        window.UploadManager.init().catch(error => {
            console.error('Failed to initialize UploadManager:', error);
        });
    });

    // Lắng nghe visibility change để refresh active uploads
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            window.UploadManager.requestActiveUploads();
        }
    });
</script> 