<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div 
        x-data="{}" 
        x-init="
            // Hide the modal's submit button
            setTimeout(() => {
                const submitBtn = document.querySelector('[data-filament-modal-submit]');
                if (submitBtn) {
                    submitBtn.style.display = 'none';
                }
            }, 100);
        "
        @media-selected="
            console.log('media-selected event received:', $event.detail);
            const fieldName = $event.detail.fieldName;
            const uuid = $event.detail.uuid;
            
            // Find the Uploadcare Alpine component by statePath
            const uploadcareElements = document.querySelectorAll('[x-data]');
            let found = false;
            
            for (const el of uploadcareElements) {
                const alpineData = Alpine.$data(el);
                if (alpineData && alpineData.statePath) {
                    // Check if statePath ends with the field name or contains it
                    if (alpineData.statePath === fieldName || alpineData.statePath.endsWith('.' + fieldName) || alpineData.statePath.endsWith('[' + fieldName + ']')) {
                        console.log('Found matching Uploadcare component, updating state');
                        // Found the Uploadcare component, update its state
                        console.log('Using UUID for update:', uuid);
                        
                        // Create a proper file object that matches the existing format
                        const newFileObject = {
                            uuid: uuid,
                            cdnUrl: 'https://ucarecdn.com/' + uuid,
                            name: 'Selected Media',
                            isImage: true,
                            mimeType: 'image/jpeg', // Default assumption
                            size: 0,
                            isSuccess: true,
                            isUploading: false,
                            isFailed: false,
                            isRemoved: false,
                            isValidationPending: false,
                            errors: [],
                            status: 'success',
                            uploadProgress: 100,
                            source: 'media-picker'
                        };
                        
                        // Try to add the file directly to the Uploadcare widget using the API
                        try {
                            console.log('Attempting to add file to Uploadcare widget via API');
                            
                            // Get the Uploadcare context and API
                            const ctx = alpineData.ctx;
                            if (ctx && typeof ctx.getAPI === 'function') {
                                const api = ctx.getAPI();
                                console.log('Got Uploadcare API:', api);
                                
                                if (api && typeof api.addFileFromUuid === 'function') {
                                    console.log('Adding file with UUID:', uuid);
                                    api.addFileFromUuid(uuid);
                                    console.log('File added to Uploadcare widget successfully');
                                } else {
                                    console.error('addFileFromUuid method not available on API');
                                }
                            } else {
                                console.error('Could not get Uploadcare API from context');
                            }
                        } catch (error) {
                            console.error('Error adding file to Uploadcare widget:', error);
                            
                            // Fallback to updateState method
                            console.log('Falling back to updateState method');
                            if (typeof alpineData.updateState === 'function') {
                                try {
                                    if (alpineData.isMultiple) {
                                        const currentFiles = alpineData.getCurrentFiles();
                                        console.log('Current files:', currentFiles);
                                        const updatedFiles = [...currentFiles, newFileObject];
                                        console.log('Updated files array:', updatedFiles);
                                        alpineData.updateState(updatedFiles);
                                    } else {
                                        console.log('Single file mode, setting:', [newFileObject]);
                                        alpineData.updateState([newFileObject]);
                                    }
                                    console.log('updateState method completed successfully');
                                } catch (updateError) {
                                    console.error('Error calling updateState:', updateError);
                                }
                            }
                        }
                        
                        console.log('State updated, closing modal');
                        found = true;
                        
                        // Close the modal
                        $wire.call('callMountedAction', ['close']);
                        break;
                    }
                }
            }
            
            if (!found) {
                console.error('Could not find Uploadcare component for field:', fieldName);
            }
        "
    >
        @livewire('backstage-uploadcare-field::media-grid-picker', [
            'fieldName' => $getFieldName(),
            'perPage' => $getPerPage()
        ], key('media-grid-picker-' . $getFieldName()))
    </div>
</x-dynamic-component>