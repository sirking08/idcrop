/**
 * ID Photo Cropper - Main Application
 * A clean implementation with proper state management
 */

// Use strict mode for better error handling
'use strict';

// Main application module
const IDPhotoCropper = (function() {
    // Configuration - all constants in one place
    const CONFIG = {
        maxFileSize: 10 * 1024 * 1024, // 10MB
        allowedTypes: ['image/jpeg', 'image/png', 'image/jpg'],
        maxFiles: 50,
        timeout: 300000, // 5 minutes
        animationFrameRate: 200 // ms
    };

    // Application state
    const state = {
        selectedFiles: [],
        fileReaders: new Set(),
        activeUploads: new Set(),
        isProcessing: false,
        isInitialized: false,
        requestId: null,
        cleanupFns: []
    };

    // DOM elements cache
    const elements = {};

    /**
     * Initialize the application
     */
    function init() {
        if (state.isInitialized) {
            console.warn('App already initialized');
            return;
        }

        try {
            // Cache DOM elements
            cacheElements();
            
            // Set up event listeners
            setupEventListeners();
            
            // Generate a unique request ID for this session
            state.requestId = 'req_' + Date.now();
            
            // Mark as initialized
            state.isInitialized = true;
            
            console.log('App initialized successfully');
        } catch (error) {
            console.error('Initialization error:', error);
            showAlert('Failed to initialize application', 'error');
        }
    }

    /**
     * Cache frequently used DOM elements
     */
    function cacheElements() {
        // Match elements with your HTML structure
        elements.dropArea = document.getElementById('dropZone');
        elements.downloadBtn = document.getElementById('downloadBtn');
        elements.downloadContainer = document.getElementById('downloadContainer');
        elements.fileInput = document.getElementById('fileInput');
        elements.browseBtn = document.getElementById('browseBtn');
        elements.processBtn = document.getElementById('processBtn');
        elements.fileList = document.getElementById('fileList');
        elements.previewContainer = document.getElementById('previewContainer');
        elements.previewImages = document.getElementById('previewImages');
        
        // Fallback for spinner and text elements
        elements.spinner = document.querySelector('.spinner-border');
        elements.submitText = document.querySelector('.submit-text') || elements.processBtn;
        elements.submitBtn = elements.processBtn;  // Use processBtn as submitBtn
        
        // Debug log to check found elements
        console.log('Cached elements:', Object.entries(elements).map(([key, el]) => ({
            key,
            exists: !!el,
            id: el?.id || 'n/a'
        })));
    }

    /**
     * Set up all event listeners
     */
    function setupEventListeners() {
        if (!elements.dropArea || !elements.fileInput) {
            throw new Error('Required DOM elements not found');
        }

        // File input change handler
        elements.fileInput.addEventListener('change', handleFileSelect, { passive: true });

        // Browse button click
        if (elements.browseBtn) {
            elements.browseBtn.addEventListener('click', () => {
                elements.fileInput.click();
            }, { passive: true });
        }

        // Drop area events
        const dropArea = elements.dropArea;
        
        // Drag over handler
        dropArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropArea.classList.add('dragover');
        }, { passive: false });

        // Drag leave handler
        dropArea.addEventListener('dragleave', (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropArea.classList.remove('dragover');
        }, { passive: true });

        // Drop handler
        dropArea.addEventListener('drop', (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropArea.classList.remove('dragover');
            
            if (e.dataTransfer?.files?.length) {
                handleFileSelect({ target: { files: e.dataTransfer.files } });
            }
        }, { passive: false });

        // Click handler for drop area
        dropArea.addEventListener('click', (e) => {
            if (!e.target.matches('input, button, a, [role="button"]')) {
                elements.fileInput.click();
            }
        }, { passive: true });

        // Process button
        if (elements.processBtn) {
            elements.processBtn.addEventListener('click', handleProcessClick, { passive: true });
        }

        // Clean up function for later
        const cleanup = () => {
            // Clean up any resources when needed
            cancelFileReads();
            cancelUploads();
        };

        // Store cleanup function
        state.cleanupFns.push(cleanup);
        
        // Set up beforeunload handler
        window.addEventListener('beforeunload', cleanup, { passive: true });
    }

    /**
     * Handle file selection
     */
    function handleFileSelect(event) {
        const files = event.target.files || (event.dataTransfer?.files || []);
        if (!files.length) return;

        const newFiles = Array.from(files);
        
        // Validate file count
        if (state.selectedFiles.length + newFiles.length > CONFIG.maxFiles) {
            showAlert(`Maximum ${CONFIG.maxFiles} files allowed`, 'warning');
            return;
        }

        const validFiles = [];
        const invalidFiles = [];

        newFiles.forEach(file => {
            // Add a unique ID to each file for tracking
            file.id = Date.now() + '-' + Math.random().toString(36).substr(2, 9);
            
            if (!CONFIG.allowedTypes.includes(file.type)) {
                invalidFiles.push(`${file.name}: Unsupported file type (${file.type})`);
            } else if (file.size > CONFIG.maxFileSize) {
                invalidFiles.push(`${file.name}: File too large (${(file.size / (1024 * 1024)).toFixed(2)}MB > ${CONFIG.maxFileSize / (1024 * 1024)}MB)`);
            } else {
                validFiles.push(file);
            }
        });

        // Show validation errors if any
        if (invalidFiles.length > 0) {
            showAlert(`Some files were rejected:\n${invalidFiles.join('\n')}`, 'warning');
        }

        // Add valid files to the selection
        if (validFiles.length > 0) {
            // Only add files that aren't already in the array
            const newValidFiles = validFiles.filter(newFile => 
                !state.selectedFiles.some(existingFile => 
                    existingFile.name === newFile.name && 
                    existingFile.size === newFile.size
                )
            );
            
            state.selectedFiles.push(...newValidFiles);
            updateFileList();
            updatePreview(newValidFiles);
        }
    }

    /**
     * Update the file list display
     */
    function updateFileList() {
        if (!elements.fileList) return;

        // Clear existing list
        elements.fileList.innerHTML = '';

        if (state.selectedFiles.length === 0) {
            const noFiles = document.createElement('p');
            noFiles.className = 'text-muted';
            noFiles.textContent = 'No files selected';
            elements.fileList.appendChild(noFiles);
            return;
        }

        // Create list items for each file
        state.selectedFiles.forEach((file, index) => {
            const item = document.createElement('div');
            item.className = 'file-item d-flex justify-content-between align-items-center mb-2';
            
            const fileInfo = document.createElement('span');
            fileInfo.textContent = `${file.name} (${(file.size / 1024).toFixed(2)} KB)`;
            
            const removeBtn = document.createElement('button');
            removeBtn.className = 'btn btn-sm btn-outline-danger';
            removeBtn.innerHTML = '&times;';
            removeBtn.onclick = () => removeFile(index);
            
            item.appendChild(fileInfo);
            item.appendChild(removeBtn);
            elements.fileList.appendChild(item);
        });

        // Update process button state
        updateProcessButton();
    }

    /**
     * Remove a file from the selection
     */
    function removeFile(index) {
        if (index >= 0 && index < state.selectedFiles.length) {
            state.selectedFiles.splice(index, 1);
            updateFileList();
            updatePreview();
        }
    }

    /**
     * Update the process button state
     */
    function updateProcessButton() {
        if (!elements.processBtn) return;
        elements.processBtn.disabled = state.selectedFiles.length === 0;
        // Hide download container when files change
        if (elements.downloadContainer) {
            elements.downloadContainer.style.display = 'none';
        }
    }

    /**
     * Show an alert message to the user
     */
    function showAlert(message, type = 'info') {
        // Create alert element if it doesn't exist
        let alertElement = document.getElementById('alert-message');
        
        if (!alertElement) {
            alertElement = document.createElement('div');
            alertElement.id = 'alert-message';
            alertElement.style.position = 'fixed';
            alertElement.style.top = '20px';
            alertElement.style.left = '50%';
            alertElement.style.transform = 'translateX(-50%)';
            alertElement.style.padding = '15px 25px';
            alertElement.style.borderRadius = '4px';
            alertElement.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
            alertElement.style.zIndex = '9999';
            alertElement.style.transition = 'all 0.3s ease';
            document.body.appendChild(alertElement);
        }
        
        // Set alert content and style based on type
        alertElement.textContent = message;
        alertElement.style.display = 'block';
        alertElement.style.opacity = '1';
        
        switch(type) {
            case 'success':
                alertElement.style.backgroundColor = '#d4edda';
                alertElement.style.color = '#155724';
                alertElement.style.border = '1px solid #c3e6cb';
                break;
            case 'error':
                alertElement.style.backgroundColor = '#f8d7da';
                alertElement.style.color = '#721c24';
                alertElement.style.border = '1px solid #f5c6cb';
                break;
            case 'warning':
                alertElement.style.backgroundColor = '#fff3cd';
                alertElement.style.color = '#856404';
                alertElement.style.border = '1px solid #ffeeba';
                break;
            default:
                alertElement.style.backgroundColor = '#d1ecf1';
                alertElement.style.color = '#0c5460';
                alertElement.style.border = '1px solid #bee5eb';
        }
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            alertElement.style.opacity = '0';
            setTimeout(() => {
                alertElement.style.display = 'none';
            }, 300);
        }, 5000);
    }

    /**
     * Handle process button click
     */
    async function handleProcessClick() {
        if (state.isProcessing || state.selectedFiles.length === 0) return;

        try {
            state.isProcessing = true;
            updateUIForProcessing(true);
            
            // Process files
            await processFiles();
            
        } catch (error) {
            console.error('Error processing files:', error);
            showAlert('Error processing files: ' + (error.message || 'Unknown error occurred'), 'error');
        } finally {
            state.isProcessing = false;
            updateUIForProcessing(false);
        }
    }

    /**
     * Update UI for processing state
     */
    function updateUIForProcessing(isProcessing) {
        if (elements.submitBtn) {
            elements.submitBtn.disabled = isProcessing;
        }
        
        if (elements.submitText) {
            elements.submitText.textContent = isProcessing ? 'Processing...' : 'Process Images';
        }
        
        // Show/hide download container based on processing state
        if (elements.downloadContainer) {
            elements.downloadContainer.style.display = isProcessing ? 'none' : 
                (elements.downloadContainer.getAttribute('data-visible') === 'true' ? 'block' : 'none');
        }

        if (elements.spinner) {
            elements.spinner.style.display = isProcessing ? 'inline-block' : 'none';
        }
    }

    /**
     * Process the selected files
     */
    /**
     * Upload selected files to the server
     */
    async function uploadFiles() {
        const formData = new FormData();
        state.selectedFiles.forEach((file) => {
            formData.append('images[]', file);
        });
        
        const response = await fetch('', {
            method: 'POST',
            body: formData,
            signal: AbortSignal.timeout(CONFIG.timeout)
        });
        if (!response.ok) {
            throw new Error(`Upload failed: ${response.status} ${response.statusText}`);
        }
        const result = await response.json();
        if (!result.success) {
            throw new Error(result.message || 'Upload failed');
        }
        return result;
    }

    /**
     * Process the selected files
     */
    async function processFiles() {
        if (!state.selectedFiles.length) {
            throw new Error('No files to process');
        }

        // STEP 1: Upload files first
        await uploadFiles();

        // STEP 2: Request processing
        
        try {
            // First check if the server is ready
            const checkResponse = await fetch('', {
                method: 'HEAD',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!checkResponse.ok) {
                throw new Error('Server is not responding properly. Please try again later.');
            }
            
            // Prepare form data for processing request
            const formData = new FormData();
            // No need to include files again, server has them stored in session
            // Add metadata
            formData.append('action', 'process_uploaded_files');
            formData.append('request_id', state.requestId);
            formData.append('timestamp', Date.now());

            const response = await fetch('', {
                method: 'POST',
                body: formData,
                signal: AbortSignal.timeout(CONFIG.timeout),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const errorText = await response.text();
                console.error('Unexpected response:', errorText);
                throw new Error('Server returned an invalid response. Please try again.');
            }

            const result = await response.json();
            
            if (!response.ok) {
                throw new Error(result.message || `Server returned ${response.status}: ${response.statusText}`);
            }
            
            if (result.success) {
                showAlert('Files processed successfully! Click Download to get your ZIP.', 'success');
                if (elements.downloadBtn && elements.downloadContainer && result.download_url) {
                    elements.downloadBtn.href = result.download_url;
                    elements.downloadContainer.style.display = 'block';
                    elements.downloadContainer.setAttribute('data-visible', 'true');
                }
                
                // Clear the file list after successful processing
                state.selectedFiles = [];
                updateFileList();
                updateProcessButton();
                
            } else {
                throw new Error(result.message || 'Failed to process files');
            }

        } catch (error) {
            console.error('File processing error:', error);
            
            // More specific error messages for common issues
            let errorMessage = error.message || 'Failed to process files';
            
            if (error.name === 'AbortError') {
                errorMessage = 'Request timed out. Please try again.';
            } else if (error.message.includes('NetworkError')) {
                errorMessage = 'Network error. Please check your connection and try again.';
            }
            
            showAlert(errorMessage, 'error');
            throw error;
        }
    }

    /**
     * Cancel any ongoing file reads
     */
    function cancelFileReads() {
        state.fileReaders.forEach(reader => {
            try {
                if (reader && typeof reader.abort === 'function') {
                    reader.abort();
                }
            } catch (e) {
                console.warn('Error aborting file reader:', e);
            }
        });
        state.fileReaders.clear();
    }

    /**
     * Cancel any ongoing uploads
     */
    function cancelUploads() {
        state.activeUploads.forEach(upload => {
            try {
                if (upload && typeof upload.abort === 'function') {
                    upload.abort();
                }
            } catch (e) {
                console.warn('Error aborting upload:', e);
            }
        });
        state.activeUploads.clear();
    }
    /**
     * Update the preview container with selected images
     */
    function updatePreview(files = state.selectedFiles) {
        if (!elements.previewContainer || !elements.previewImages) {
            console.warn('Preview container or images container not found');
            return;
        }

        // Clear previous previews
        elements.previewImages.innerHTML = '';

        if (!files.length) {
            elements.previewContainer.style.display = 'none';
            return;
        }

        // Show the preview container
        elements.previewContainer.style.display = 'block';

        // Add each file to the preview
        files.forEach((file, index) => {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const previewItem = document.createElement('div');
                previewItem.className = 'col-6 col-md-4 col-lg-3 mb-3';
                previewItem.innerHTML = `
                    <div class="card h-100">
                        <img src="${e.target.result}" class="card-img-top preview-image" alt="${file.name}" style="height: 200px; object-fit: contain; background: #f8f9fa;">
                        <div class="card-body p-2">
                            <p class="card-text small text-truncate mb-1" title="${file.name}">${file.name}</p>
                            <p class="card-text small text-muted mb-0">${(file.size / 1024).toFixed(2)} KB</p>
                        </div>
                        <div class="card-footer bg-transparent border-top-0 pt-0">
                            <button class="btn btn-sm btn-outline-primary w-100 preview-btn" data-index="${index}">
                                <i class="fas fa-eye me-1"></i> Preview
                            </button>
                        </div>
                    </div>
                `;
                
                previewImages.appendChild(previewItem);
                
                // Add click handler for the preview button
                const previewBtn = previewItem.querySelector('.preview-btn');
                if (previewBtn) {
                    previewBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        showImagePreview(e.target.closest('.card').querySelector('img').src, file.name);
                    });
                }
            };
            
            reader.readAsDataURL(file);
        });
    }

    /**
     * Show a modal with the full-size image preview
     */
    function showImagePreview(imageSrc, imageName) {
        // Create modal HTML if it doesn't exist
        let modal = document.getElementById('imagePreviewModal');
        
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'imagePreviewModal';
            modal.className = 'modal fade';
            modal.tabIndex = '-1';
            modal.innerHTML = `
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">${imageName || 'Image Preview'}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body text-center">
                            <img src="${imageSrc}" class="img-fluid" alt="Preview">
                        </div>
                        <div class="modal-footer">
                            <a href="${imageSrc}" class="btn btn-primary" download="${imageName || 'preview'}">
                                <i class="fas fa-download me-2"></i> Download
                            </a>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        } else {
            // Update existing modal content
            modal.querySelector('.modal-title').textContent = imageName || 'Image Preview';
            const img = modal.querySelector('.modal-body img');
            if (img) img.src = imageSrc;
            const downloadBtn = modal.querySelector('.modal-footer a');
            if (downloadBtn) {
                downloadBtn.href = imageSrc;
                downloadBtn.download = imageName || 'preview';
            }
        }
        
        // Show the modal
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    }

    /**
     * Clean up resources and reset state
     */
    function cleanup() {
        // Cancel any ongoing file reads
        cancelFileReads();
        
        // Cancel any ongoing uploads
        cancelUploads();
        
        // Clear selected files
        state.selectedFiles = [];
        
        // Clear the file input
        if (elements.fileInput) {
            elements.fileInput.value = '';
        }
        
        // Clear the file list
        if (elements.fileList) {
            elements.fileList.innerHTML = '';
        }
        
        // Hide the preview container
        if (elements.previewContainer) {
            elements.previewContainer.style.display = 'none';
        }
        
        // Reset processing state
        state.isProcessing = false;
    }

    // Public API
    return {
        init,
        cleanup,
        getState: () => ({ ...state })
    };
})();

// Make available globally
window.IDPhotoCropper = IDPhotoCropper;

// Export for ES modules if needed
if (typeof module !== 'undefined' && module.exports) {
    module.exports = IDPhotoCropper;
}
