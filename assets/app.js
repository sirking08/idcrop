// assets/js/app.js
(function() {
    'use strict';

    // Configuration
    const CONFIG = {
        maxFileSize: 10 * 1024 * 1024, // 10MB
        allowedTypes: ['image/jpeg', 'image/png', 'image/jpg'],
        maxFiles: 50
    };

    // State
    let selectedFiles = [];
    let formSubmitted = false;
    let fileReaders = []; // Track FileReader instances for cleanup

    // DOM Elements
    const elements = {
        // File upload elements (required)
        dropArea: document.getElementById('dropZone'),
        fileInput: document.getElementById('fileInput'),
        fileList: document.getElementById('fileList'),
        processBtn: document.getElementById('processBtn'),
        processingIndicator: document.getElementById('processingIndicator'),
        
        // UI feedback elements (required)
        infoAlert: document.getElementById('infoAlert'),
        infoMessage: document.getElementById('infoMessage'),
        errorAlert: document.getElementById('errorAlert'),
        errorMessage: document.getElementById('errorMessage'),
        previewContainer: document.getElementById('previewContainer'),
        previewImages: document.getElementById('previewImages'),
        downloadBtn: document.getElementById('downloadBtn'),
        
        // Progress elements (optional)
        progressBar: document.querySelector('.progress-bar'),
        progressText: document.querySelector('.progress-text'),
        progressContainer: document.querySelector('.progress-container'),
        
        // Form elements (optional)
        submitBtn: document.querySelector('button[type="submit"]'),
        submitText: document.querySelector('.submit-text'),
        spinner: document.querySelector('.spinner-border')
    };
    
    // Create fallback elements for optional components
    if (!elements.progressContainer) {
        const progressDiv = document.createElement('div');
        progressDiv.className = 'progress-container mt-3';
        progressDiv.style.display = 'none';
        progressDiv.innerHTML = `
            <div class="progress">
                <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
            </div>
            <div class="progress-text text-center mt-2 small text-muted">0%</div>
        `;
        document.querySelector('.upload-container').appendChild(progressDiv);
        
        elements.progressContainer = progressDiv;
        elements.progressBar = progressDiv.querySelector('.progress-bar');
        elements.progressText = progressDiv.querySelector('.progress-text');
    }
    
    // Create a fallback spinner if not present
    if (!elements.spinner && elements.submitBtn) {
        const spinner = document.createElement('span');
        spinner.className = 'spinner-border spinner-border-sm d-none';
        spinner.setAttribute('role', 'status');
        spinner.setAttribute('aria-hidden', 'true');
        elements.submitBtn.prepend(' ');
        elements.submitBtn.prepend(spinner);
        elements.spinner = spinner;
    }
    
    // Destructure for easier access
    const {
        dropArea, fileInput, fileList, processBtn, processingIndicator,
        infoAlert, infoMessage, errorAlert, errorMessage, previewContainer,
        previewImages, downloadBtn, progressBar, progressText, progressContainer,
        submitBtn, submitText, spinner
    } = elements;
    
    // Initialize the application
    function init() {
        console.log('=== IDCrop App Initialized ===');
        
        // Check for critical elements
        const criticalElements = [
            'dropArea', 'fileInput', 'fileList', 'processBtn',
            'infoAlert', 'infoMessage', 'errorAlert', 'errorMessage',
            'previewContainer', 'previewImages', 'downloadBtn'
        ];
        
        const missingCriticalElements = criticalElements.filter(
            name => !elements[name]
        );
        
        if (missingCriticalElements.length > 0) {
            console.error('Missing critical elements:', missingCriticalElements.join(', '));
            const errorMsg = `Failed to initialize. Missing required UI elements. Please refresh the page.`;
            if (errorMessage) {
                errorMessage.textContent = errorMsg;
                errorAlert.style.display = 'block';
            } else {
                alert(errorMsg);
            }
            return;
        }
        
        // Set up event listeners
        try {
            setupEventListeners();
            console.log('Event listeners initialized successfully');
            
            // Show a welcome message if infoMessage is available
            if (infoMessage) {
                infoMessage.textContent = 'Drag and drop your photos or click to browse';
                infoAlert.style.display = 'block';
            }
        } catch (error) {
            console.error('Error initializing event listeners:', error);
            const errorMsg = 'Failed to initialize. Please refresh the page.';
            if (errorMessage) {
                errorMessage.textContent = errorMsg;
                errorAlert.style.display = 'block';
            } else {
                alert(errorMsg);
            }
        }
    }

    // Set up all event listeners
    function setupEventListeners() {
        if (!dropArea || !fileInput) {
            console.error('Required DOM elements not found');
            return false;
        }
        
        console.log('Setting up event listeners');

        // Clean up any existing event listeners first
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropArea.removeEventListener(eventName, preventDefaults, false);
            document.body.removeEventListener(eventName, preventDefaults, false);
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.removeEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.removeEventListener(eventName, unhighlight, false);
        });

        dropArea.removeEventListener('drop', handleDrop, false);
        dropArea.removeEventListener('click', handleDropAreaClick);
        fileInput.removeEventListener('change', handleFileSelect, false);
        
        // Set up drag and drop events
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, preventDefaults, false);
            document.body.addEventListener(eventName, preventDefaults, false);
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, unhighlight, false);
        });

        // Handle drop events
        dropArea.addEventListener('drop', handleDrop, false);
        
        // Handle click on drop area
        dropArea.addEventListener('click', handleDropAreaClick);
        
        // Handle browse button click
        const browseBtn = document.getElementById('browseBtn');
        if (browseBtn) {
            // Remove any existing click handlers
            const newBrowseBtn = browseBtn.cloneNode(true);
            browseBtn.parentNode.replaceChild(newBrowseBtn, browseBtn);
            
            newBrowseBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                console.log('Browse button clicked');
                fileInput.click();
            });
        }
        
        // Handle file selection
        fileInput.addEventListener('change', handleFileSelect, false);
        
        // Handle process button click
        const processBtn = document.getElementById('processBtn');
        if (processBtn) {
            // Remove any existing click handlers
            const newProcessBtn = processBtn.cloneNode(true);
            processBtn.parentNode.replaceChild(newProcessBtn, processBtn);
            
            newProcessBtn.addEventListener('click', (e) => {
                e.preventDefault();
                if (selectedFiles.length > 0) {
                    processUploadedFiles();
                } else {
                    showAlert('Please select files to process', 'warning');
                }
            });
        }
    }
    
    // Prevent default drag and drop behaviors
    function preventDefaults(e) {
        // Only prevent default for drag and drop related events
        if (e.type === 'dragenter' || e.type === 'dragover' || e.type === 'drop') {
            e.preventDefault();
            e.stopPropagation();
        }
    }
    
    // Highlight drop area when item is dragged over it
    function highlight(e) {
        dropArea.classList.add('dragover');
        console.log('Drag over drop area');
    }
    
    function unhighlight(e) {
        dropArea.classList.remove('dragover');
        console.log('Drag left drop area');
    }
    
    // Handle dropped files
    function handleDrop(e) {
        console.log('=== Files Dropped ===');
        const dt = e.dataTransfer;
        const files = dt.files;
        
        if (files.length > 0) {
            processFiles(files);
        }
    }

    // Handle file selection via input
    function handleFileSelect(e) {
        console.log('=== Files Selected ===');
        if (this.files && this.files.length > 0) {
            processFiles(this.files);
            this.value = ''; // Reset to allow selecting the same file again
        }
    }

    // Process and validate files
    function processFiles(files) {
        const newFiles = Array.from(files);
        
        // Validate file count
        if (selectedFiles.length + newFiles.length > CONFIG.maxFiles) {
            showAlert(`Maximum ${CONFIG.maxFiles} files allowed`, 'warning');
            return;
        }

        const validFiles = [];
        const invalidFiles = [];

        newFiles.forEach(file => {
            if (!CONFIG.allowedTypes.includes(file.type)) {
                invalidFiles.push(`${file.name}: Unsupported file type (${file.type})`);
            } else if (file.size > CONFIG.maxFileSize) {
                invalidFiles.push(`${file.name}: File too large (max ${formatFileSize(CONFIG.maxFileSize)})`);
            } else {
                validFiles.push(file);
            }
        });

        if (invalidFiles.length > 0) {
            showAlert(`Some files were rejected:\n${invalidFiles.join('\n')}`, 'warning');
        }

        if (validFiles.length > 0) {
            selectedFiles = [...selectedFiles, ...validFiles];
            updateFileInput();
            updateFileList();
        }
    }

    // Update the file input with current selection
    function updateFileInput() {
        try {
            if (!fileInput) {
                console.warn('File input element not found');
                return;
            }
            
            // Only update if we have files
            if (selectedFiles.length > 0) {
                const dataTransfer = new DataTransfer();
                // Filter out any invalid file entries
                selectedFiles.forEach(file => {
                    if (file instanceof File) {
                        dataTransfer.items.add(file);
                    }
                });
                
                // Only update if we have valid files
                if (dataTransfer.files.length > 0) {
                    fileInput.files = dataTransfer.files;
                } else {
                    fileInput.value = '';
                }
            } else {
                fileInput.value = '';
            }
        } catch (error) {
            console.error('Error updating file input:', error);
            // Fallback to basic reset if there's an error
            if (fileInput) {
                fileInput.value = '';
            }
        }
    }
    
    
    /**
     * Process uploaded files through the server
     * Handles both the initial upload and processing phases
     */
    function processUploadedFiles() {
        console.log('=== Processing Files ===');
        
        if (selectedFiles.length === 0) {
            showAlert('No files to process', 'warning');
            return;
        }
        
        if (formSubmitted) {
            console.warn('Processing already in progress, ignoring duplicate request');
            return;
        }
        
        // Validate all files before submission
        const invalidFiles = selectedFiles.filter(file => 
            !CONFIG.allowedTypes.includes(file.type) || file.size > CONFIG.maxFileSize
        );
        
        if (invalidFiles.length > 0) {
            const errorList = invalidFiles.map(file => 
                `${file.name}: ${
                    !CONFIG.allowedTypes.includes(file.type) ? 
                    'Unsupported file type' : 
                    `File too large (max ${formatFileSize(CONFIG.maxFileSize)})`
                }`
            ).join('\n');
            
            showAlert(`Cannot process the following files:\n${errorList}`, 'danger');
            return false;
        }
        
        if (formSubmitted) {
            console.warn('Processing already in progress, ignoring duplicate request');
            return;
        }
        
        console.log('Selected files count:', selectedFiles.length);
        selectedFiles.forEach((file, i) => {
            console.log(`File ${i + 1}:`, file.name, `(${formatFileSize(file.size)}, ${file.type})`);
        });
        
        // Show loading state
        submitBtn.disabled = true;
        submitText.textContent = 'Uploading...';
        spinner.classList.remove('d-none');
        updateProgress(0);
        
        // Create FormData and append files
        const formData = new FormData();
        console.log('Creating FormData with files:');
        
        // Add CSRF token if available
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        if (csrfToken) {
            formData.append('_token', csrfToken);
        }
        
        // Add files to FormData
        selectedFiles.forEach((file, i) => {
            console.log(`- File ${i + 1}:`, file.name, `(${formatFileSize(file.size)})`);
            formData.append('images[]', file, file.name);
        });
        
        // Initialize XHR for file upload
        const xhr = new XMLHttpRequest();
        const url = window.location.pathname.endsWith('/') ? window.location.pathname : window.location.pathname + '/';
        xhr.open('POST', url, true);
        
        // Set timeout (2 minutes for upload)
        xhr.timeout = 120000;
        
        // Progress tracking with throttling for upload
        let lastUpdate = 0;
        xhr.upload.onprogress = function(e) {
            if (e.lengthComputable) {
                const now = Date.now();
                // Throttle updates to once every 200ms
                if (now - lastUpdate > 200) {
                    // Only go up to 90% for upload phase
                    const percentComplete = Math.round((e.loaded / e.total) * 90);
                    updateProgress(percentComplete);
                    console.log(`Upload progress: ${percentComplete}%`);
                    lastUpdate = now;
                }
            }
        };

        // Handle response
        xhr.onload = function() {
            try {
                console.log('=== Upload Complete ===');
                console.log('Status:', this.status, this.statusText);
                
                let response;
                try {
                    response = JSON.parse(this.responseText);
                    console.log('Upload response:', response);
                } catch (e) {
                    console.error('Failed to parse JSON response:', this.responseText);
                    throw new Error('Invalid server response format during upload');
                }
                
                if (this.status >= 200 && this.status < 300 && response.success) {
                    // Upload successful, now process the files
                    processUploadedFiles();
                } else {
                    // Upload failed
                    const errorMsg = response?.message || 'Upload failed';
                    showAlert(`Upload failed: ${errorMsg}`, 'danger');
                    resetUI();
                }
            } catch (e) {
                console.error('Error handling upload response:', e);
                showAlert('Error processing upload: ' + e.message, 'danger');
                resetUI();
            }
        };
        
        xhr.onerror = function() {
            console.error('=== Upload Failed ===');
            console.error('Status:', this.status, this.statusText);
            console.error('Response:', this.responseText);
            showAlert('An error occurred while uploading the files. Please try again.', 'danger');
            resetUI();
        };
        
        xhr.ontimeout = function() {
            console.error('=== Upload Timeout ===');
            showAlert('The upload timed out. Please check your connection and try again.', 'danger');
            resetUI();
        };
        
        // Send the upload request
        console.log('Starting file upload to:', url);
        xhr.send(formData);
        formSubmitted = true;
    }
    
    // Process uploaded files
    function processUploadedFiles() {
        console.log('=== Starting File Processing ===');
        submitText.textContent = 'Processing images...';
        updateProgress(90); // Start processing at 90%
        
        // Initialize XHR for processing
        const xhr = new XMLHttpRequest();
        const url = window.location.pathname.endsWith('/') ? window.location.pathname : window.location.pathname + '/';
        const formData = new FormData();
        
        // Add CSRF token if available
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        if (csrfToken) {
            formData.append('_token', csrfToken);
        }
        
        formData.append('action', 'process_uploaded_files');
        
        xhr.open('POST', url, true);
        xhr.timeout = 300000; // 5 minutes for processing
        
        // Progress tracking for processing
        let lastProgress = 90;
        let progressInterval = setInterval(() => {
            // Gradually increase progress to 95% over time to show activity
            if (lastProgress < 95) {
                lastProgress += 0.5;
                updateProgress(lastProgress);
            }
        }, 500);
        
        xhr.onload = function() {
            clearInterval(progressInterval);
            handleXhrResponse.call(this);
        };
        
        xhr.onerror = handleXhrError;
        xhr.ontimeout = handleXhrTimeout;
        
        // Send the processing request
        console.log('Starting file processing');
        xhr.send(formData);
    }
    
    // Handle XHR response for processing phase
    function handleXhrResponse() {
        const xhr = this; // 'this' is the XHR object
        let response;
        
        console.log('=== Processing Response ===');
        console.log('Status:', xhr.status, xhr.statusText);
        
        try {
            try {
                response = JSON.parse(xhr.responseText);
                console.log('Processing response:', response);
            } catch (e) {
                console.error('Failed to parse JSON response:', xhr.responseText);
                throw new Error('Invalid server response format during processing');
            }
            
            if (xhr.status >= 200 && xhr.status < 300) {
                if (response.success) {
                    updateProgress(100); // Complete the progress bar
                    showAlert(response.message, 'success');
                    
                    if (response.downloadUrl) {
                        console.log('Download URL:', response.downloadUrl);
                        // Small delay to show completion before download starts
                        setTimeout(() => {
                            handleFileDownload(response.downloadUrl);
                            // Reset the form after successful download
                            resetForm();
                        }, 500);
                    } else {
                        // If no download URL, just reset after a short delay
                        setTimeout(resetForm, 1000);
                    }
                    
                    return; // Success, exit early
                } else {
                    const errorMsg = response.message || 'Processing failed';
                    showAlert(errorMsg, 'danger');
                    
                    if (response.errors?.length) {
                        console.error('Processing errors:', response.errors);
                        // Show first 3 errors to avoid overwhelming the user
                        const errorList = response.errors.slice(0, 3).map(err => 
                            `• ${err.file ? `${err.file}: ` : ''}${err.message || 'Unknown error'}`
                        ).join('\n');
                        
                        if (response.errors.length > 3) {
                            errorList += `\n...and ${response.errors.length - 3} more errors`;
                        }
                        
                        showAlert(`Processing completed with errors:\n${errorList}`, 'warning', 10000);
                    }
                }
            } else {
                const errorMsg = response?.message || xhr.statusText || 'Server error';
                showAlert(`Server error during processing (${xhr.status}): ${errorMsg}`, 'danger');
            }
        } catch (e) {
            console.error('Error processing response:', e);
            showAlert('Error processing server response: ' + e.message, 'danger');
        } finally {
            // Only reset UI if not handling a successful download
            if (!(xhr.status >= 200 && xhr.status < 300 && response?.success && response?.downloadUrl)) {
                resetUI();
            }
        }
    }
    
    // Handle XHR errors
    function handleXhrError() {
        const xhr = this; // 'this' is the XHR object
        
        // Determine if this was an upload or processing error
        const isProcessing = xhr.responseURL && xhr.responseURL.includes('action=process_uploaded_files');
        const step = isProcessing ? 'processing' : 'upload';
        
        console.error(`=== ${step.charAt(0).toUpperCase() + step.slice(1)} Failed ===`);
        console.error('Status:', xhr.status, xhr.statusText);
        console.error('Response:', xhr.responseText);
        
        let errorMessage = `An error occurred during ${step}. Please try again.`;
        
        try {
            const response = JSON.parse(xhr.responseText);
            if (response?.message) {
                errorMessage = response.message;
            }
            
            if (response?.errors?.length) {
                // Show first 3 errors to avoid overwhelming the user
                const errorList = response.errors.slice(0, 3).map(err => 
                    `• ${err.file ? `${err.file}: ` : ''}${err.message || 'Unknown error'}`
                ).join('\n');
                
                if (response.errors.length > 3) {
                    errorList += `\n...and ${response.errors.length - 3} more errors`;
                }
                
                errorMessage = `${errorMessage}\n\n${errorList}`;
            }
        } catch (e) {
            console.error('Error parsing error response:', e);
        }
        
        showAlert(errorMessage, 'danger');
        resetUI();
    }
    
    // Handle XHR timeout
    function handleXhrTimeout() {
        const xhr = this; // 'this' is the XHR object
        
        // Determine if this was an upload or processing timeout
        const isProcessing = xhr.responseURL && xhr.responseURL.includes('action=process_uploaded_files');
        const step = isProcessing ? 'processing' : 'upload';
        
        console.error(`=== ${step.charAt(0).toUpperCase() + step.slice(1)} Timeout ===`);
        
        let errorMessage = `The ${step} timed out. Please try again with fewer or smaller files.`;
        
        if (isProcessing) {
            errorMessage += ' The server is still processing your files in the background. ';
            errorMessage += 'Please check back in a few minutes or contact support if the issue persists.';
        }
        
        showAlert(errorMessage, 'warning');
        resetUI();
    }
    
    // Handle file download
    function handleFileDownload(url) {
        const link = document.createElement('a');
        link.href = url;
        link.download = url.split('/').pop() || 'id_photos.zip';
        link.setAttribute('download', ''); // For cross-browser compatibility
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
    
    // Reset form to initial state
    function resetForm() {
        selectedFiles = [];
        fileInput.value = '';
        updateFileList();
    }
    
    // Reset UI elements to initial state
    function resetUI() {
        submitBtn.disabled = false;
        submitText.textContent = 'Process Images';
        spinner.classList.add('d-none');
        progressContainer.classList.add('d-none');
        formSubmitted = false;
    }
    
    // Handle click on drop area
    function handleDropAreaClick(e) {
        // Ignore clicks on the browse button or file input
        if (e.target.closest('#browseBtn') || e.target === fileInput) {
            return;
        }
        
        // Only trigger file input if clicking directly on the drop zone or its text
        if (e.target === dropArea || 
            e.target.classList.contains('drop-zone-text') ||
            e.target.closest('.drop-zone-text')) {
            e.preventDefault();
            e.stopPropagation();
            fileInput.click();
        }
    }
    
    // Update the file list display
    function updateFileList() {
        console.log('=== Updating File List ===');
        if (!fileList) {
            console.error('fileList element not found in the DOM');
            return;
        }
        
        // Get the preview container
        const previewContainer = document.getElementById('previewContainer');
        
        // Clean up existing file readers
        cleanupFileReaders();
        
        // Clear existing content safely
        while (fileList.firstChild) {
            fileList.removeChild(fileList.firstChild);
        }
        
        // Clear preview container
        if (previewContainer) {
            while (previewContainer.firstChild) {
                previewContainer.removeChild(previewContainer.firstChild);
            }
        }
        
        if (!selectedFiles.length) {
            const noFilesSelected = document.createElement('p');
            noFilesSelected.className = 'text-muted';
            noFilesSelected.textContent = 'No files selected';
            fileList.appendChild(noFilesSelected);
            return;
        }
        
        const header = document.createElement('h3');
        header.className = 'h6 mb-3';
        header.textContent = `Selected Files (${selectedFiles.length}/${CONFIG.maxFiles})`;
        fileList.appendChild(header);
        
        const list = document.createElement('ul');
        list.className = 'list-group mb-3';
        list.setAttribute('aria-label', 'List of selected files');
        fileList.appendChild(list);
        
        // Add files to the list
        selectedFiles.forEach((file, index) => {
            const listItem = document.createElement('li');
            listItem.className = 'list-group-item d-flex justify-content-between align-items-center';
            listItem.setAttribute('data-file-index', index);
            
            // File info
            const fileInfo = document.createElement('div');
            fileInfo.className = 'd-flex align-items-center';
            
            // File icon based on type
            const fileIcon = document.createElement('i');
            fileIcon.className = 'fas fa-file-image me-2';
            fileIcon.setAttribute('aria-hidden', 'true');
            
            // File name with truncation
            const fileName = document.createElement('span');
            fileName.className = 'text-truncate me-2';
            fileName.style.maxWidth = '200px';
            fileName.textContent = file.name;
            fileName.title = file.name;
            
            fileInfo.appendChild(fileIcon);
            fileInfo.appendChild(fileName);
            
            // File size and remove button
            const fileActions = document.createElement('div');
            fileActions.className = 'd-flex align-items-center';
            
            const fileSize = document.createElement('span');
            fileSize.className = 'badge bg-secondary me-2';
            fileSize.textContent = formatFileSize(file.size);
            
            const previewToggle = document.createElement('button');
            previewToggle.type = 'button';
            previewToggle.className = 'btn btn-sm btn-outline-primary me-2';
            previewToggle.innerHTML = '<i class="fas fa-eye me-1"></i> Preview';
            previewToggle.setAttribute('aria-label', `Preview ${file.name}`);
            previewToggle.title = 'Preview image';
            previewToggle.onclick = (e) => togglePreview(file, index, e);
            
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn btn-sm btn-outline-danger';
            removeBtn.innerHTML = '<i class="fas fa-trash-alt me-1"></i> Remove';
            removeBtn.setAttribute('aria-label', `Remove ${file.name}`);
            removeBtn.title = 'Remove file';
            removeBtn.onclick = (e) => removeFile(index, e);
            
            fileActions.appendChild(fileSize);
            fileActions.appendChild(previewToggle);
            fileActions.appendChild(removeBtn);
            
            listItem.appendChild(fileInfo);
            listItem.appendChild(fileActions);
            list.appendChild(listItem);
        });
        
        // Add a clear all button if there are files
        if (selectedFiles.length > 1) {
            const clearAllBtn = document.createElement('button');
            clearAllBtn.type = 'button';
            clearAllBtn.className = 'btn btn-sm btn-outline-secondary w-100 mt-2';
            clearAllBtn.textContent = 'Clear All';
            clearAllBtn.onclick = () => {
                selectedFiles = [];
                fileInput.value = '';
                updateFileList();
            };
            fileList.appendChild(clearAllBtn);
        }
    }
    
    // Toggle preview for a file
function togglePreview(file, index, event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    console.log('Toggling preview for file:', file.name);
    
    // Get or create preview elements
    let previewContainer = document.getElementById('previewContainer');
    let previewImages = document.getElementById('previewImages');
    
    // Create elements if they don't exist
    if (!previewContainer) {
        console.log('Creating preview container...');
        previewContainer = document.createElement('div');
        previewContainer.id = 'previewContainer';
        previewContainer.className = 'preview-container';
        previewContainer.style.display = 'none'; // Start hidden
        document.querySelector('.container').appendChild(previewContainer);
    }
    
    if (!previewImages) {
        console.log('Creating preview images container...');
        previewImages = document.createElement('div');
        previewImages.id = 'previewImages';
        previewContainer.appendChild(previewImages);
    }

    // Check if we're toggling the same file
    const isSameFile = previewContainer.getAttribute('data-current-file') === file.name;
    const isPreviewVisible = previewContainer.style.display !== 'none';
    
    if (isPreviewVisible && isSameFile) {
        console.log('Hiding preview for', file.name);
        previewContainer.style.display = 'none';
        previewContainer.removeAttribute('data-current-file');
        previewContainer.innerHTML = ''; // Clear the container
        return;
    }

    console.log('Showing preview for:', file.name);
    
    // Clear existing preview
    previewImages.innerHTML = '';
    
    // Set current file
    previewContainer.setAttribute('data-current-file', file.name);
    
    // Create loading indicator
    const loadingDiv = document.createElement('div');
    loadingDiv.className = 'text-center py-4';
    loadingDiv.innerHTML = `
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2 mb-0">Loading preview...</p>
    `;

    // Create image element
    const img = document.createElement('img');
    img.className = 'preview-image';
    img.alt = 'Preview: ' + file.name;
    img.style.display = 'none'; // Hide until loaded
    
    // Create card structure
    const card = document.createElement('div');
    card.className = 'card shadow-sm';
    
    const cardHeader = document.createElement('div');
    cardHeader.className = 'card-header bg-white d-flex justify-content-between align-items-center';
    cardHeader.innerHTML = `
        <h5 class="mb-0">Preview: ${file.name}</h5>
        <button type="button" class="btn-close" aria-label="Close"></button>
    `;
    
    const cardBody = document.createElement('div');
    cardBody.className = 'card-body p-0';
    
    const imgContainer = document.createElement('div');
    imgContainer.className = 'img-preview-container';
    imgContainer.appendChild(loadingDiv);
    imgContainer.appendChild(img);
    
    // Build the card
    cardBody.appendChild(imgContainer);
    card.appendChild(cardHeader);
    card.appendChild(cardBody);
    
    // Create column
    const col = document.createElement('div');
    col.className = 'col-md-8 col-lg-6 mx-auto';
    col.appendChild(card);
    
    // Add to preview
    previewImages.appendChild(col);
    
    // Show the container
    previewContainer.style.display = 'block';
    
    // Add close button handler
    const closeBtn = cardHeader.querySelector('.btn-close');
    closeBtn.onclick = (e) => {
        e.stopPropagation();
        previewContainer.style.display = 'none';
    };

    // Create and configure FileReader
    const reader = new FileReader();
    fileReaders.push(reader); // Track for cleanup
    
    reader.onload = function(e) {
        console.log('File read complete, setting image source');
        img.src = e.target.result;
    };
    
    reader.onerror = function() {
        console.error('Error reading file');
        loadingDiv.innerHTML = `
            <div class="text-danger">
                <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                <p class="mb-0">Error loading image preview</p>
            </div>
        `;
    };
    
    img.onload = function() {
        console.log('Image loaded successfully');
        loadingDiv.style.display = 'none';
        img.style.display = 'block';
        
        // Force reflow
        void img.offsetHeight;
        
        // Scroll to preview
        setTimeout(() => {
            previewContainer.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest'
            });
        }, 100);
    };
    
    img.onerror = function() {
        console.error('Error loading image');
        loadingDiv.innerHTML = `
            <div class="text-danger">
                <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                <p class="mb-0">Error loading image preview</p>
            </div>
        `;
    };
    
    // Start reading the file
    console.log('Starting to read file as data URL');
    try {
        reader.readAsDataURL(file);
    } catch (error) {
        console.error('Error reading file:', error);
        loadingDiv.innerHTML = `
            <div class="text-danger">
                <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                <p class="mb-0">Error: ${error.message}</p>
            </div>
        `;
    }
}
    
    // Remove a file from the selection
    function removeFile(index, event) {
        // Prevent any default behavior
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        
        try {
            // Remove the file from the selected files array
            if (index >= 0 && index < selectedFiles.length) {
                // Clean up any preview for this file
                const previewContainer = document.getElementById('previewContainer');
                if (previewContainer) {
                    const currentFileIndex = previewContainer.getAttribute('data-file-index');
                    if (currentFileIndex === index.toString()) {
                        // Hide the container and clear its contents
                        previewContainer.style.display = 'none';
                        previewContainer.removeAttribute('data-file-index');
                        previewContainer.innerHTML = '';
                        
                        // Remove any loading indicators
                        const loadingDivs = previewContainer.querySelectorAll('.text-center.py-4');
                        loadingDivs.forEach(div => div.remove());
                    }
                }
                
                // Remove the file
                selectedFiles.splice(index, 1);
                
                // Update the UI first
                updateFileList();
                
                // Then update the file input (if needed)
                if (selectedFiles.length > 0) {
                    updateFileInput();
                } else {
                    // If no files left, reset the input
                    if (fileInput) {
                        fileInput.value = '';
                    }
                }
                
                // Show feedback to the user
                showAlert('File removed successfully', 'info');
            } else {
                console.error('Invalid file index:', index);
                showAlert('Error: Could not remove file', 'error');
            }
        } catch (error) {
            console.error('Error removing file:', error);
            showAlert('Error removing file: ' + error.message, 'error');
        }
    }
    
    // Clean up file readers to prevent memory leaks
    function cleanupFileReaders() {
        fileReaders.forEach(reader => {
            if (reader && typeof reader.abort === 'function') {
                try {
                    reader.abort();
                } catch (e) {
                    console.warn('Error aborting file reader:', e);
                }
            }
        });
        fileReaders = [];
    }
    
    // Format file size in a human-readable format
    function formatFileSize(bytes) {
        if (typeof bytes !== 'number' || isNaN(bytes)) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.min(
            Math.floor(Math.log(bytes) / Math.log(k)),
            sizes.length - 1
        );
        
        // Use toLocaleString for proper number formatting
        return `${parseFloat((bytes / Math.pow(k, i)).toFixed(2)).toLocaleString()} ${sizes[i]}`;
    }
    
    // Update progress bar and text
    function updateProgress(percent) {
        if (!progressBar || !progressText) return;
        
        const safePercent = Math.min(100, Math.max(0, percent));
        progressBar.style.width = `${safePercent}%`;
        progressBar.setAttribute('aria-valuenow', safePercent);
        progressBar.setAttribute('aria-valuetext', `${safePercent}% complete`);
        progressText.textContent = `Processing: ${safePercent}%`;
        
        // Update progress container visibility
        if (safePercent > 0 && safePercent < 100) {
            progressContainer.classList.remove('d-none');
        } else if (safePercent >= 100) {
            // Keep it visible for a moment after completion
            setTimeout(() => {
                progressContainer.classList.add('d-none');
            }, 1000);
        }
    }

    // Show an alert message to the user
    function showAlert(message, type = 'info') {
        if (!message) return;
        
        // Create a unique ID for this alert
        const alertId = 'alert-' + Date.now();
        const alertTypes = {
            'info': { icon: 'info-circle', class: 'info' },
            'success': { icon: 'check-circle', class: 'success' },
            'warning': { icon: 'exclamation-triangle', class: 'warning' },
            'danger': { icon: 'exclamation-circle', class: 'danger' }
        };
        
        // Get alert container or create one if it doesn't exist
        let alertContainer = document.getElementById('alert-container');
        if (!alertContainer) {
            alertContainer = document.createElement('div');
            alertContainer.id = 'alert-container';
            alertContainer.className = 'position-fixed top-0 end-0 p-3';
            alertContainer.style.zIndex = '1100';
            alertContainer.setAttribute('aria-live', 'polite');
            alertContainer.setAttribute('aria-atomic', 'true');
            document.body.appendChild(alertContainer);
        }
        
        // Create the alert element
        const alertType = alertTypes[type] || alertTypes.info;
        const alertDiv = document.createElement('div');
        alertDiv.id = alertId;
        alertDiv.className = `alert alert-${alertType.className} alert-dismissible fade show d-flex align-items-center`;
        alertDiv.role = 'alert';
        
        // Add icon
        const icon = document.createElement('i');
        icon.className = `fas fa-${alertType.icon} me-2`;
        icon.setAttribute('aria-hidden', 'true');
        
        // Add message
        const messageDiv = document.createElement('div');
        messageDiv.textContent = message;
        
        // Add close button
        const closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'btn-close ms-auto';
        closeBtn.setAttribute('data-bs-dismiss', 'alert');
        closeBtn.setAttribute('aria-label', 'Close');
        
        // Assemble the alert
        alertDiv.appendChild(icon);
        alertDiv.appendChild(messageDiv);
        alertDiv.appendChild(closeBtn);
        
        // Add to container
        alertContainer.appendChild(alertDiv);
        
        // Auto-remove after delay
        setTimeout(() => {
            const alert = document.getElementById(alertId);
            if (alert) {
                if (bootstrap && bootstrap.Alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                } else {
                    alert.remove();
                }
            }
        }, type === 'success' ? 5000 : 10000);
        
        // Return the alert element in case it needs to be managed
        return alertDiv;
    }

    // Handle form submission
    function handleFormSubmit(e) {
        e.preventDefault();
        processUploadedFiles();
    }

    // Initialize the application when the DOM is fully loaded
    window.initializeApp = function() {
        console.log('DOM fully loaded, initializing...');
        
        // Initialize all elements
        const elementIds = {
            dropZone: 'dropZone',
            fileInput: 'fileInput',
            fileList: 'fileList',
            processBtn: 'processBtn',
            processingIndicator: 'processingIndicator',
            infoAlert: 'infoAlert',
            infoMessage: 'infoMessage',
            errorAlert: 'errorAlert',
            errorMessage: 'errorMessage',
            previewContainer: 'previewContainer',
            previewImages: 'previewImages',
            downloadBtn: 'downloadBtn'
        };
        
        // Initialize elements object
        Object.keys(elementIds).forEach(key => {
            elements[key] = document.getElementById(elementIds[key]);
        });
        
        // Check for required elements
        const requiredElements = Object.keys(elementIds);
        const missingElements = requiredElements.filter(id => !elements[id]);
        
        if (missingElements.length > 0) {
            console.error('Missing required elements:', missingElements.join(', '));
            
            // Try to create missing preview container elements
            if (missingElements.includes('previewContainer') || missingElements.includes('previewImages')) {
                console.log('Attempting to create missing preview elements...');
                const uploadContainer = document.querySelector('.upload-container');
                if (uploadContainer) {
                    const previewDiv = document.createElement('div');
                    previewDiv.id = 'previewContainer';
                    previewDiv.className = 'preview-container mt-4';
                    previewDiv.style.display = 'none';
                    
                    const card = document.createElement('div');
                    card.className = 'card';
                    
                    const cardHeader = document.createElement('div');
                    cardHeader.className = 'card-header';
                    
                    const cardTitle = document.createElement('h5');
                    cardTitle.className = 'card-title mb-0';
                    cardTitle.textContent = 'Preview';
                    
                    const cardBody = document.createElement('div');
                    cardBody.className = 'card-body';
                    
                    const previewImages = document.createElement('div');
                    previewImages.id = 'previewImages';
                    previewImages.className = 'row';
                    
                    cardHeader.appendChild(cardTitle);
                    cardBody.appendChild(previewImages);
                    
                    card.appendChild(cardHeader);
                    card.appendChild(cardBody);
                    
                    // Add download button in footer
                    const cardFooter = document.createElement('div');
                    cardFooter.className = 'card-footer text-end';
                    
                    const downloadBtn = document.createElement('a');
                    downloadBtn.href = '#';
                    downloadBtn.className = 'btn btn-primary';
                    downloadBtn.id = 'downloadBtn';
                    downloadBtn.style.display = 'none';
                    downloadBtn.innerHTML = '<i class="fas fa-download me-2"></i>Download All';
                    
                    cardFooter.appendChild(downloadBtn);
                    card.appendChild(cardFooter);
                    
                    previewDiv.appendChild(card);
                    
                    // Insert after the file list
                    const fileList = document.getElementById('fileList');
                    if (fileList && fileList.parentNode) {
                        fileList.parentNode.insertBefore(previewDiv, fileList.nextSibling);
                    } else {
                        uploadContainer.appendChild(previewDiv);
                    }
                    
                    // Update elements reference
                    elements.previewContainer = previewDiv;
                    elements.previewImages = previewImages;
                    elements.downloadBtn = downloadBtn;
                    
                    console.log('Created preview container elements dynamically');
                }
            }
        }
        
        // Initialize the application
        init();
        
        // Set up form submission
        const uploadForm = document.getElementById('uploadForm');
        if (uploadForm) {
            uploadForm.addEventListener('submit', handleFormSubmit);
        }
        
        // Set up process button
        if (elements.processBtn) {
            elements.processBtn.addEventListener('click', (e) => {
                e.preventDefault();
                processUploadedFiles();
            });
        }
        
        // Log final element status
        console.log('Element initialization complete');
        console.log('Preview container:', elements.previewContainer);
        console.log('Preview images container:', elements.previewImages);
    }
    
    // Wait for DOM to be fully loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeApp);
    } else {
        // DOMContentLoaded has already fired
        setTimeout(initializeApp, 0);
    }
})();