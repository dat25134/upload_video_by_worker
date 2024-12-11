self.addEventListener('message', async (e) => {
    const { chunk, chunkIndex, sessionId, file, csrfToken } = e.data;

    const formData = new FormData();
    formData.append('chunk', file.slice(chunk.start, chunk.end));
    formData.append('chunkIndex', chunkIndex);
    formData.append('sessionId', sessionId);

    try {
        const response = await fetch('/upload/chunk', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken
            },
            body: formData
        });

        if (!response.ok) throw new Error('Chunk upload failed');

        self.postMessage({
            success: true,
            chunkIndex: chunkIndex
        });
    } catch (error) {
        self.postMessage({
            success: false,
            chunkIndex: chunkIndex,
            error: error.message
        });
    }
});
