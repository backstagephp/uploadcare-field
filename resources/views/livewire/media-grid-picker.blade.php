<div class="space-y-4" 
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
     ">
    <div class="flex items-center justify-between">
        <div class="text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ __('Select Media') }}
        </div>
        <div class="text-xs text-gray-500 dark:text-gray-400">
            {{ __('Showing') }} {{ $this->mediaItems->firstItem() ?? 0 }} {{ __('to') }} {{ $this->mediaItems->lastItem() ?? 0 }} {{ __('of') }} {{ $this->mediaItems->total() }} {{ __('results') }}
        </div>
    </div>
    
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3 max-h-96 overflow-y-auto">
        @forelse($this->mediaItems as $media)
            <div 
                wire:click="selectMedia({{ json_encode($media) }})"
                class="relative group cursor-pointer rounded-lg border-2 transition-all duration-200 hover:shadow-md {{ $selectedMediaId === $media['id'] ? 'border-blue-500 ring-2 ring-blue-200' : 'border-gray-200 hover:border-gray-300 dark:border-gray-700 dark:hover:border-gray-600' }}"
            >
                @if($media['is_image'] && $media['cdn_url'])
                    <div class="aspect-square rounded-md overflow-hidden bg-gray-100 dark:bg-gray-800">
                        <img 
                            src="{{ $media['cdn_url'] }}" 
                            alt="{{ $media['filename'] }}"
                            class="w-full h-full object-cover"
                            loading="lazy"
                        />
                    </div>
                @else
                    <div class="aspect-square rounded-md bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                @endif
                
                <div class="p-2">
                    <div class="text-xs font-medium text-gray-900 dark:text-gray-100 truncate" title="{{ $media['filename'] }}">{{ $media['filename'] }}</div>
                    @if($media['is_image'] && $media['width'] && $media['height'])
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $media['width'] }}×{{ $media['height'] }}</div>
                    @endif
                </div>
                
                @if($selectedMediaId === $media['id'])
                    <div class="absolute top-1 right-1">
                        <div class="w-5 h-5 bg-blue-500 rounded-full flex items-center justify-center">
                            <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <div class="col-span-full text-center py-8 text-gray-500 dark:text-gray-400">
                <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <p>{{ __('No media files found') }}</p>
            </div>
        @endforelse
    </div>

    @if($selectedMediaId)
        <div class="flex items-center justify-center pt-4 border-t border-gray-200 dark:border-gray-700">
            <button 
                type="button"
                wire:click="confirmSelection"
                onclick="console.log('Select button clicked'); $wire.call('confirmSelection');"
                style="display: inline-block; padding: 8px 16px; background-color: #2563eb; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500; box-shadow: 0 2px 4px -1px rgba(0, 0, 0, 0.1); transition: all 0.2s;"
                onmouseover="this.style.backgroundColor='#1d4ed8'; this.style.transform='translateY(-1px)'; this.style.boxShadow='0 6px 8px -1px rgba(0, 0, 0, 0.15)'"
                onmouseout="this.style.backgroundColor='#2563eb'; this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px -1px rgba(0, 0, 0, 0.1)'"
            >
                Select
            </button>
        </div>
    @endif

    @if($this->mediaItems->hasPages())
        <div class="flex items-center justify-between pt-4 border-t border-gray-200 dark:border-gray-700">
            <div class="flex items-center space-x-2">
                <button 
                    wire:click.prevent="previousPage"
                    wire:loading.attr="disabled"
                    @disabled($this->mediaItems->onFirstPage())
                    class="px-3 py-1 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {{ __('Previous') }}
                </button>
                
                <span class="text-sm text-gray-700 dark:text-gray-300">
                    {{ __('Page') }} {{ $this->mediaItems->currentPage() }} {{ __('of') }} {{ $this->mediaItems->lastPage() }}
                </span>
                
                <button 
                    wire:click.prevent="nextPage"
                    wire:loading.attr="disabled"
                    @disabled(!$this->mediaItems->hasMorePages())
                    class="px-3 py-1 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {{ __('Next') }}
                </button>
            </div>
            
            <div class="flex items-center space-x-2">
                <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Per page') }}:</span>
                <select 
                    wire:change.prevent="updatePerPage(parseInt($event.target.value))"
                    wire:loading.attr="disabled"
                    class="text-sm border border-gray-300 rounded-md px-2 py-1 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <option value="6" @selected($perPage === 6)>6</option>
                    <option value="12" @selected($perPage === 12)>12</option>
                    <option value="24" @selected($perPage === 24)>24</option>
                    <option value="48" @selected($perPage === 48)>48</option>
                </select>
            </div>
        </div>
    @endif
</div>