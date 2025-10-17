<div class="space-y-4">
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
                class="relative group cursor-pointer rounded-lg border-2 transition-all duration-200 hover:shadow-md border-gray-200 hover:border-gray-300 dark:border-gray-700 dark:hover:border-gray-600"
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
