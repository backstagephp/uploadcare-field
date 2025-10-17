<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div x-data="{
        handleMediaSelected(event) {
            const { fieldName, media } = event.detail;
            console.log('Media selected:', media);
            console.log('Field name:', fieldName);
            console.log('CDN URL:', media.cdn_url);
            
            const cdnUrl = media.cdn_url;
            
            if (!cdnUrl) {
                console.error('No CDN URL available for selected media');
                return;
            }
            
            let uuid = cdnUrl;
            if (cdnUrl.includes('ucarecdn.com/')) {
                const match = cdnUrl.match(/ucarecdn\.com\/([^\/\?]+)/);
                if (match) {
                    uuid = match[1];
                }
            }
            
            console.log('Using UUID for file selection:', uuid);
            
            const hiddenInputs = document.querySelectorAll('input[type=hidden]');
            let targetInput = null;
            
            for (const input of hiddenInputs) {
                if (input.name && input.name.includes(fieldName)) {
                    targetInput = input;
                    break;
                }
            }
            
            if (targetInput) {
                console.log('Found target input:', targetInput);
                targetInput.value = uuid;
                targetInput.dispatchEvent(new Event('change', { bubbles: true }));
                targetInput.dispatchEvent(new Event('input', { bubbles: true }));
                console.log('Set input value to:', uuid);
            } else {
                console.warn('Could not find target input for field:', fieldName);
            }
        }
    }" 
    @media-selected="handleMediaSelected($event)"
    >
        @livewire('backstage-uploadcare-field::media-grid-picker', [
            'fieldName' => $getFieldName(),
            'perPage' => $getPerPage()
        ], key('media-grid-picker-' . $getFieldName()))
    </div>
</x-dynamic-component>