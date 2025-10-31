<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div 
        x-data="{}"
        @set-hidden-field="
            console.log('set-hidden-field event received:', $event.detail);
            const fieldName = $event.detail.fieldName;
            const value = $event.detail.value;
            
            // Find the hidden input in the modal
            const modal = $el.closest('[data-filament-modal]');
            if (modal) {
                const hiddenInput = modal.querySelector('input[name=' + fieldName + ']');
                if (hiddenInput) {
                    hiddenInput.value = value;
                    console.log('Set hidden field value to:', value);
                }
            }
        "
    >
        @livewire('backstage-uploadcare-field::media-grid-picker', [
            'fieldName' => $getFieldName(),
            'perPage' => $getPerPage()
        ], key('media-grid-picker-' . $getFieldName()))
    </div>
</x-dynamic-component>
