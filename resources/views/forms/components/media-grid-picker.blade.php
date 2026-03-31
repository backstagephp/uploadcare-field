<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div 
        x-data="{}"
        @set-hidden-field.window="
            const d = $event.detail;
            const fn = d.fieldName;
            const v = d.value;
            
            // Find the modal - try multiple selectors
            let m = document.querySelector('[role=dialog]:not([hidden])') ||
                   document.querySelector('.fi-modal:not([hidden])') ||
                   document.querySelector('[data-filament-modal]:not([hidden])');
            
            if (!m) {
                // Find any visible modal
                const modals = document.querySelectorAll('[role=dialog], .fi-modal, [data-filament-modal]');
                for (let modal of modals) {
                    const style = window.getComputedStyle(modal);
                    if (style.display !== 'none' && style.visibility !== 'hidden') {
                        m = modal;
                        break;
                    }
                }
            }
            
            if (m) {
                // Find the hidden input field - try multiple selectors
                let input = null;
                
                // Try by ID first (Filament uses mountedActionSchema0.fieldName format)
                input = m.querySelector('input[id*=' + fn + ']');
                
                // Try wire:model
                if (!input) {
                    const wireModelInputs = m.querySelectorAll('input[wire\\:model]');
                    for (let i of wireModelInputs) {
                        const wireModel = i.getAttribute('wire:model');
                        if (wireModel && wireModel.includes(fn)) {
                            input = i;
                            break;
                        }
                    }
                }
                
                // Fallback to name attribute
                if (!input) {
                    input = m.querySelector('input[name*=' + fn + ']') || 
                           m.querySelector('input[name=' + fn + ']');
                }
                
                if (input) {
                    // Handle arrays by JSON encoding them, strings as-is
                    input.value = Array.isArray(v) ? JSON.stringify(v) : v;
                    
                    // Trigger multiple events to ensure Filament picks up the change
                    input.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
                    input.dispatchEvent(new Event('blur', { bubbles: true, cancelable: true }));
                    
                    // Force a re-render by dispatching a custom event
                    input.dispatchEvent(new CustomEvent('livewire:update', { bubbles: true }));
                }
            }
        "
    >
        @livewire('backstage-uploadcare-field::media-grid-picker', [
            'fieldName' => $getFieldName(),
            'perPage' => $getPerPage(),
            'multiple' => $getMultiple(),
            'acceptedFileTypes' => $getAcceptedFileTypes()
        ], key('media-grid-picker-' . $getFieldName() . '-' . uniqid()))
    </div>
</x-dynamic-component>