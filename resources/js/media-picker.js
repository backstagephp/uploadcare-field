// Alpine.js data function for media picker pagination
window.mediaPickerData = function(initialData) {
    return {
        ...initialData,
        
        async goToNextPage() {
            if (this.hasMorePages && !this.loading) {
                this.loading = true;
                try {
                    const response = await fetch(this.paginateUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            page: this.currentPage + 1,
                            per_page: this.perPage
                        })
                    });
                    const data = await response.json();
                    this.currentPage = data.current_page;
                    this.lastPage = data.last_page;
                    this.from = data.from;
                    this.to = data.to;
                    this.hasPages = data.has_pages;
                    this.onFirstPage = data.on_first_page;
                    this.hasMorePages = data.has_more_pages;
                    this.mediaItems = data.items;
                } catch (error) {
                    console.error('Error loading next page:', error);
                } finally {
                    this.loading = false;
                }
            }
        },
        
        async goToPreviousPage() {
            if (!this.onFirstPage && !this.loading) {
                this.loading = true;
                try {
                    const response = await fetch(this.paginateUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            page: this.currentPage - 1,
                            per_page: this.perPage
                        })
                    });
                    const data = await response.json();
                    this.currentPage = data.current_page;
                    this.lastPage = data.last_page;
                    this.from = data.from;
                    this.to = data.to;
                    this.hasPages = data.has_pages;
                    this.onFirstPage = data.on_first_page;
                    this.hasMorePages = data.has_more_pages;
                    this.mediaItems = data.items;
                } catch (error) {
                    console.error('Error loading previous page:', error);
                } finally {
                    this.loading = false;
                }
            }
        },
        
        async changePerPage(newPerPage) {
            if (newPerPage !== this.perPage) {
                this.loading = true;
                try {
                    const response = await fetch(this.paginateUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            page: 1,
                            per_page: newPerPage
                        })
                    });
                    const data = await response.json();
                    this.currentPage = data.current_page;
                    this.lastPage = data.last_page;
                    this.perPage = data.per_page;
                    this.from = data.from;
                    this.to = data.to;
                    this.hasPages = data.has_pages;
                    this.onFirstPage = data.on_first_page;
                    this.hasMorePages = data.has_more_pages;
                    this.mediaItems = data.items;
                } catch (error) {
                    console.error('Error changing per page:', error);
                } finally {
                    this.loading = false;
                }
            }
        }
    };
};

// Note: All file selection logic is now handled directly in the MediaGridPicker component
// No external event listeners needed to avoid Livewire conflicts

        // Function to handle file selection
        function handleFileSelection(field, cdnUrl) {
                // Check if cdnUrl is valid
                if (!cdnUrl) {
                    console.error('No CDN URL provided for field:', field);
                    return;
                }
                
                // Extract UUID from CDN URL
                // Use the already extracted UUID
                
                console.log('Using UUID for file selection:', uuid);
                
                // Method 1: Try to find and use Uploadcare API
        // Escape field name for CSS selectors (ULIDs start with numbers)
        const escapedField = CSS.escape ? CSS.escape(field) : field.replace(/[^a-zA-Z0-9_-]/g, '\\$&');
        
        const uploadcareSelectors = [
            `uc-upload-ctx-provider[ctx-name="${field}"]`,
            `[data-uploadcare-context="${field}"]`,
            `[data-field-name="${field}"]`,
            `[data-field="${field}"]`,
            `#${escapedField}`,
            `[name="${field}"]`
        ];
        
        let uploadcareElement = null;
        for (const selector of uploadcareSelectors) {
            uploadcareElement = document.querySelector(selector);
            if (uploadcareElement) {
                console.log('Found Uploadcare element with selector:', selector);
                break;
            }
        }
        
        if (uploadcareElement) {
            try {
                // Try to get the Uploadcare API
                let api = null;
                
                if (uploadcareElement.getAPI) {
                    api = uploadcareElement.getAPI();
                } else if (uploadcareElement.uploadcare) {
                    api = uploadcareElement.uploadcare;
                } else if (window.uploadcare) {
                    api = window.uploadcare;
                }
                
                if (api && api.addFileFromCdnUrl) {
                    console.log('Adding file to Uploadcare via API:', cdnUrl);
                    api.addFileFromCdnUrl(cdnUrl);
                    return;
                }
                
                // Try alternative API methods
                if (api && api.addFile) {
                    console.log('Adding file to Uploadcare via addFile:', cdnUrl);
                    api.addFile(cdnUrl);
                    return;
                }
                
                // Try to find the hidden input within the Uploadcare element
                const hiddenInput = uploadcareElement.querySelector('input[type="hidden"]');
                if (hiddenInput) {
                    console.log('Found hidden input in Uploadcare element, setting value to:', uuid);
                    hiddenInput.value = uuid;
                    hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
                    hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
                    return;
                }
                
                // Try to trigger file uploaded event
                if (uploadcareElement.dispatchEvent) {
                    const fileUploadedEvent = new CustomEvent('file-uploaded', {
                        detail: { uuid: uuid, cdnUrl: cdnUrl },
                        bubbles: true
                    });
                    uploadcareElement.dispatchEvent(fileUploadedEvent);
                    console.log('Dispatched file-uploaded event with UUID:', uuid);
                    return;
                }
            } catch (error) {
                console.error('Error using Uploadcare API:', error);
            }
        }
        
        // Method 2: Try to update Livewire state directly
        try {
            // Find the Livewire component that contains this field
            const livewireComponent = document.querySelector('[wire\\:id]');
            if (livewireComponent && window.Livewire) {
                const componentId = livewireComponent.getAttribute('wire:id');
                const component = window.Livewire.find(componentId);
                
                if (component) {
                    console.log('Found Livewire component:', componentId);
                    
                    // Use the already extracted UUID
                    
                    // Try to set the field value using Livewire's set method
                    console.log('Setting Livewire field value:', field, uuid);
                    component.set(field, uuid);
                    
                    // Also try to trigger a refresh
                    component.$refresh();
                    
                    return;
                }
            }
        } catch (error) {
            console.error('Error updating Livewire state:', error);
        }
        
        // Method 3: Try to find the hidden input field and set its value
        const hiddenInputSelectors = [
            `input[name="${field}"][type="hidden"]`,
            `#${escapedField}`,
            `[name="${field}"]`
        ];
        
        let hiddenInput = null;
        for (const selector of hiddenInputSelectors) {
            hiddenInput = document.querySelector(selector);
            if (hiddenInput) {
                console.log('Found hidden input with selector:', selector);
                break;
            }
        }
        
        if (hiddenInput) {
            try {
                // Use the already extracted UUID
                console.log('Setting hidden input value to:', uuid);
                hiddenInput.value = uuid;
                
                // Trigger multiple events to ensure Filament/Livewire picks up the change
                hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
                hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
                hiddenInput.dispatchEvent(new Event('blur', { bubbles: true }));
                
                // Try to trigger Livewire update
                if (window.Livewire) {
                    window.Livewire.emit('input', field, uuid);
                }
                
                return;
            } catch (error) {
                console.error('Error setting hidden input value:', error);
            }
        }
        
        // Method 4: Try to create a file object and trigger file selection
        try {
            // Extract filename from CDN URL
            const urlParts = cdnUrl.split('/');
            const filename = urlParts[urlParts.length - 1].split('?')[0] || 'selected-file';
            
            // Create a blob from the CDN URL (this might not work due to CORS)
            fetch(cdnUrl)
                .then(response => response.blob())
                .then(blob => {
                    // Create a File object
                    const file = new File([blob], filename, { type: blob.type });
                    
                    // Try to find file input and set the file
                    const fileInputs = document.querySelectorAll(`input[type="file"][name*="${field}"], input[type="file"]`);
                    if (fileInputs.length > 0) {
                        const fileInput = fileInputs[0];
                        
                        // Create a DataTransfer object to set the file
                        const dataTransfer = new DataTransfer();
                        dataTransfer.items.add(file);
                        fileInput.files = dataTransfer.files;
                        
                        // Trigger change event
                        fileInput.dispatchEvent(new Event('change', { bubbles: true }));
                        
                        console.log('Set file on file input:', fileInput);
                        return;
                    }
                })
                .catch(error => {
                    console.log('Could not fetch file from CDN URL (CORS issue):', error);
                });
        } catch (error) {
            console.error('Error creating file object:', error);
        }
        
        // Method 5: Try to find inputs with the exact field name in their ID (for nested form structures)
        // Look for inputs that end with the field name or have it as the last part
        const idInputs = document.querySelectorAll(`input[id$="${field}"], input[id*=".${field}"], input[id*="${field}."]`);
        if (idInputs.length > 0) {
            console.log('Found input fields with exact field name in ID:', field, idInputs);
            
            // Try to set the value on the first input found
            const firstInput = idInputs[0];
            try {
                // Use the already extracted UUID
                
                firstInput.value = uuid;
                firstInput.dispatchEvent(new Event('change', { bubbles: true }));
                firstInput.dispatchEvent(new Event('input', { bubbles: true }));
                
                console.log('Set value on input field with ID:', firstInput);
                return;
            } catch (error) {
                console.error('Error setting input field value:', error);
            }
        }
        
        // Method 5b: Try to find inputs with the field name as a complete segment in the ID
        const allInputs = document.querySelectorAll('input[id]');
        const matchingInputs = Array.from(allInputs).filter(input => {
            const id = input.id;
            // Check if the field name appears as a complete segment (surrounded by dots or at the end)
            return id.includes(`.${field}.`) || id.endsWith(`.${field}`) || id === field;
        });
        
        if (matchingInputs.length > 0) {
            console.log('Found input fields with field name as complete segment in ID:', field, matchingInputs);
            
            const firstInput = matchingInputs[0];
            try {
                // Use the already extracted UUID
                
                firstInput.value = uuid;
                firstInput.dispatchEvent(new Event('change', { bubbles: true }));
                firstInput.dispatchEvent(new Event('input', { bubbles: true }));
                
                console.log('Set value on input field with complete segment ID:', firstInput);
                return;
            } catch (error) {
                console.error('Error setting input field value:', error);
            }
        }
        
        // Method 6: Try to find any input field with the field name
        const nameInputs = document.querySelectorAll(`input[name*="${field}"], textarea[name*="${field}"]`);
        if (nameInputs.length > 0) {
            console.log('Found input fields for field:', field, nameInputs);
            
            // Try to set the value on the first input found
            const firstInput = nameInputs[0];
            try {
                // Use the already extracted UUID
                
                firstInput.value = uuid;
                firstInput.dispatchEvent(new Event('change', { bubbles: true }));
                firstInput.dispatchEvent(new Event('input', { bubbles: true }));
                
                console.log('Set value on input field:', firstInput);
            } catch (error) {
                console.error('Error setting input field value:', error);
            }
        }
        
        console.warn('Could not find Uploadcare element or input field for:', field);
        console.log('Available elements:', document.querySelectorAll('[data-field-name], [name], [id]'));
}

