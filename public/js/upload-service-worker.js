let activeUploads = new Map();
let csrfToken = null;

// Thêm BroadcastChannel
const uploadChannel = new BroadcastChannel('upload-channel');

self.addEventListener('install', async (event) => {
    event.waitUntil(
        Promise.all([
            self.skipWaiting(),
            initializeDB()
        ])
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(clients.claim());
});

self.addEventListener('message', async function(e) {
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

        case 'GET_ACTIVE_UPLOADS':
            try {
                const activeUploads = await getAllActiveUploads();
                broadcastMessage({
                    type: 'ACTIVE_UPLOADS',
                    uploads: activeUploads
                });
            } catch (error) {
                console.error('Error handling GET_ACTIVE_UPLOADS:', error);
                broadcastMessage({
                    type: 'ERROR',
                    error: 'Failed to get active uploads'
                });
            }
            break;
    }
});

async function handleFileUpload(uploadId, file, fileName, chunkSize) {
    const chunks = Math.ceil(file.size / chunkSize);
    const uploadInfo = activeUploads.get(uploadId);
    let consecutiveErrors = 0;
    const MAX_RETRIES = 3;
    const MAX_CONSECUTIVE_ERRORS = 5;

    const cleanup = async () => {
        activeUploads.delete(uploadId);
    };

    try {
        for (let i = 0; i < chunks; i++) {
            if (uploadInfo.cancelled) {
                await cleanup();
                broadcastMessage({
                    type: 'UPLOAD_CANCELLED',
                    uploadId,
                    fileName
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
                
                await updateUploadProgress(uploadId, {
                    currentChunk: i + 1,
                    progress,
                    status: 'in_progress'
                });

                broadcastMessage({
                    type: 'PROGRESS',
                    uploadId,
                    progress,
                    fileName
                });

                consecutiveErrors = 0;
            } catch (error) {
                consecutiveErrors++;
                console.error(`Chunk upload error (${consecutiveErrors}/${MAX_CONSECUTIVE_ERRORS}):`, error);

                if (consecutiveErrors >= MAX_CONSECUTIVE_ERRORS) {
                    throw new Error('Too many consecutive errors. Terminating all uploads.');
                }

                if (i > 0 && consecutiveErrors < MAX_RETRIES) {
                    i--;
                    await new Promise(resolve => setTimeout(resolve, 1000 * consecutiveErrors));
                    continue;
                }

                throw error;
            }

            await new Promise(resolve => setTimeout(resolve, 10));
        }

        await cleanup();
        broadcastMessage({
            type: 'COMPLETE',
            uploadId,
            fileName
        });

    } catch (error) {
        if (error.message.includes('Terminating all uploads') || 
            error.message.includes('Network') || 
            error.message.includes('Storage quota exceeded')) {
            await terminateAllUploads(error);
        } else {
            await cleanup();
            broadcastMessage({
                type: 'ERROR',
                uploadId,
                error: error.message
            });
        }
    }
}

function broadcastMessage(message) {
    // Đảm bảo message là serializable
    const cloneableMessage = JSON.parse(JSON.stringify(message));
    
    try {
        // Gửi qua BroadcastChannel
        uploadChannel.postMessage(cloneableMessage);
        
        // Gửi qua clients API
        self.clients.matchAll().then(clients => {
            clients.forEach(client => client.postMessage(cloneableMessage));
        });
    } catch (error) {
        console.error('Error broadcasting message:', error, message);
    }
}

// IndexedDB functions for persistence
const DB_NAME = 'UploadDB';
const STORE_NAME = 'uploads';

async function openDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, 1);
        
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);
        
        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            if (!db.objectStoreNames.contains(STORE_NAME)) {
                const store = db.createObjectStore(STORE_NAME, { keyPath: 'uploadId' });
                store.createIndex('status', 'status');
                store.createIndex('lastUpdated', 'lastUpdated');
            }
        };
    });
}

async function initializeDB() {
    try {
        const db = await openDB();
        return true;
    } catch (error) {
        console.error('Failed to initialize IndexedDB:', error);
        return false;
    }
}

async function updateUploadProgress(uploadId, data) {
    try {
        const db = await openDB();
        const tx = db.transaction(STORE_NAME, 'readwrite');
        const store = tx.objectStore(STORE_NAME);
        
        const uploadData = {
            uploadId,
            ...data,
            lastUpdated: Date.now()
        };
        
        await store.put(uploadData);
        
        // Broadcast cập nhật với plain object
        broadcastMessage({
            type: 'PROGRESS',
            uploadId,
            progress: data.progress,
            fileName: data.fileName,
            status: data.status
        });
    } catch (error) {
        console.error('Error updating upload progress:', error);
        throw error;
    }
}

function isUploadActive(uploadId) {
    return activeUploads.has(uploadId);
}

function getUploadInfo(uploadId) {
    return activeUploads.get(uploadId);
}

async function terminateAllUploads(error) {
    const uploads = Array.from(activeUploads.entries());
    
    for (const [uploadId, uploadInfo] of uploads) {
        try {
            await updateUploadProgress(uploadId, {
                status: 'error',
                error: error.message || 'Upload terminated due to critical error'
            });

            broadcastMessage({
                type: 'ERROR',
                uploadId,
                error: error.message || 'Upload terminated due to critical error',
                isCritical: true
            });

            activeUploads.delete(uploadId);
        } catch (e) {
            console.error('Error during cleanup:', e);
        }
    }

    try {
        const db = await openDB();
        const tx = db.transaction(STORE_NAME, 'readwrite');
        await tx.objectStore(STORE_NAME).clear();
    } catch (e) {
        console.error('Error clearing IndexedDB:', e);
    }

    broadcastMessage({
        type: 'CRITICAL_ERROR',
        error: error.message || 'A critical error occurred. All uploads have been terminated.'
    });

    self.registration.unregister()
        .then(() => console.log('Service Worker unregistered due to critical error'))
        .catch(e => console.error('Error unregistering Service Worker:', e));
}

self.addEventListener('error', (event) => {
    console.error('Service Worker error:', event.error);
    terminateAllUploads(event.error);
});

self.addEventListener('unhandledrejection', (event) => {
    console.error('Service Worker unhandled rejection:', event.reason);
    terminateAllUploads(event.reason);
});

// Thêm hàm để kiểm tra trạng thái upload
async function validateUpload(uploadId) {
    try {
        const db = await openDB();
        const tx = db.transaction(STORE_NAME, 'readonly');
        const store = tx.objectStore(STORE_NAME);
        const upload = await store.get(uploadId);

        if (!upload) return false;

        // Kiểm tra xem upload có thực sự đang active
        const isActive = upload.status === 'in_progress' && 
                        upload.progress < 100 &&
                        upload.lastUpdated > (Date.now() - (60 * 60 * 1000)); // 1 hour timeout

        if (!isActive) {
            // Cleanup nếu không active
            const deleteTx = db.transaction(STORE_NAME, 'readwrite');
            await deleteTx.objectStore(STORE_NAME).delete(uploadId);
        }

        return isActive;
    } catch (error) {
        console.error('Error validating upload:', error);
        return false;
    }
}

// Cập nhật getAllActiveUploads
async function getAllActiveUploads() {
    try {
        const db = await openDB();
        const tx = db.transaction(STORE_NAME, 'readonly');
        const store = tx.objectStore(STORE_NAME);
        
        return new Promise(async (resolve, reject) => {
            const request = store.index('status').getAll('in_progress');
            
            request.onsuccess = async () => {
                const uploads = [];
                for (const upload of request.result) {
                    // Chỉ trả về uploads thực sự đang active
                    if (await validateUpload(upload.uploadId)) {
                        uploads.push({
                            uploadId: upload.uploadId,
                            fileName: upload.fileName,
                            progress: upload.progress,
                            status: upload.status,
                            lastUpdated: upload.lastUpdated
                        });
                    }
                }
                resolve(uploads);
            };
            
            request.onerror = () => reject(request.error);
        });
    } catch (error) {
        console.error('Error getting active uploads:', error);
        return [];
    }
}

// Thêm periodic cleanup trong service worker
setInterval(async () => {
    try {
        const db = await openDB();
        const tx = db.transaction(STORE_NAME, 'readwrite');
        const store = tx.objectStore(STORE_NAME);
        const uploads = await store.getAll();

        const oneHourAgo = Date.now() - (60 * 60 * 1000);

        for (const upload of uploads) {
            if (upload.lastUpdated < oneHourAgo || 
                upload.status !== 'in_progress' || 
                upload.progress === 100) {
                await store.delete(upload.uploadId);
            }
        }
    } catch (error) {
        console.error('Error in periodic cleanup:', error);
    }
}, 5 * 60 * 1000); // Every 5 minutes
 