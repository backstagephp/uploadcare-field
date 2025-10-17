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
                        const cdnUrl = 'https://ucarecdn.com/' + uuid;
                        
                        if (typeof alpineData.updateState === 'function') {
                            console.log('Using updateState method');
                            if (alpineData.isMultiple) {
                                const currentFiles = alpineData.getCurrentFiles();
                                const updatedFiles = [...currentFiles, cdnUrl];
                                alpineData.updateState(updatedFiles);
                            } else {
                                alpineData.updateState([cdnUrl]);
                            }
                        } else if (typeof alpineData.uploadedFiles !== 'undefined') {
                            console.log('Using direct uploadedFiles update');
                            const currentFiles = alpineData.uploadedFiles ? JSON.parse(alpineData.uploadedFiles) : [];
                            const updatedFiles = [...currentFiles, cdnUrl];
                            alpineData.uploadedFiles = JSON.stringify(updatedFiles);
                            alpineData.state = alpineData.uploadedFiles;
                        } else {
                            console.log('Using direct state update');
                            const currentFiles = alpineData.state ? JSON.parse(alpineData.state) : [];
                            const updatedFiles = [...currentFiles, cdnUrl];
                            alpineData.state = JSON.stringify(updatedFiles);
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