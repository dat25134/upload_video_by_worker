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

<script>
    // Tạo một đối tượng global để quản lý toast
    window.ToastUpload = {
        show: function(fileName) {
            const toast = document.getElementById('uploadToast');
            const fileNameElement = document.getElementById('fileName');
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            
            fileNameElement.textContent = fileName;
            toast.style.display = 'block';
            progressBar.style.width = '0%';
            progressText.textContent = '0%';
        },
        
        updateProgress: function(progress) {
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            
            progressBar.style.width = `${progress}%`;
            progressText.textContent = `${progress}%`;
            
            if (progress === 100) {
                setTimeout(() => {
                    this.hide();
                }, 2000);
            }
        },
        
        hide: function() {
            const toast = document.getElementById('uploadToast');
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            
            toast.style.display = 'none';
            progressBar.style.width = '0%';
            progressText.textContent = '0%';
        }
    };
</script> 