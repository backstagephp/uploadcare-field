@php
    $mediaItems = $getMediaItems();
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div class="space-y-4" x-data="{
        selectedMediaId: null,
        currentPage: {{ $mediaItems->currentPage() }},
        lastPage: {{ $mediaItems->lastPage() }},
        perPage: {{ $mediaItems->perPage() }},
        total: {{ $mediaItems->total() }},
        from: {{ $mediaItems->firstItem() ?? 0 }},
        to: {{ $mediaItems->lastItem() ?? 0 }},
        hasPages: {{ $mediaItems->hasPages() ? 'true' : 'false' }},
        onFirstPage: {{ $mediaItems->onFirstPage() ? 'true' : 'false' }},
        hasMorePages: {{ $mediaItems->hasMorePages() ? 'true' : 'false' }},
        mediaItems: @js($mediaItems->items()),
        loading: false,
        
        async goToPage(page) {
            if (this.loading || page < 1 || page > this.lastPage) return;
            
            this.loading = true;
            this.currentPage = page;
            
            try {
                // For now, we'll just simulate the pagination by reloading the current page
                // In a real implementation, you'd make an AJAX request to get new data
                await new Promise(resolve => setTimeout(resolve, 300)); // Simulate loading
                
                // Update the pagination state
                this.onFirstPage = page === 1;
                this.hasMorePages = page < this.lastPage;
                this.from = ((page - 1) * this.perPage) + 1;
                this.to = Math.min(page * this.perPage, this.total);
                
            } catch (error) {
                console.error('Error loading page:', error);
            } finally {
                this.loading = false;
            }
        },
        
        async changePerPage(perPage) {
            this.perPage = parseInt(perPage);
            this.lastPage = Math.ceil(this.total / this.perPage);
            await this.goToPage(1);
        }
    }">
        <div class="flex items-center justify-between">
            <div class="text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ __('Select Media') }}
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400">
                {{ __('Showing') }} <span x-text="from"></span> {{ __('to') }} <span x-text="to"></span> {{ __('of') }} <span x-text="total"></span> {{ __('results') }}
            </div>
        </div>
        
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3 max-h-96 overflow-y-auto" x-show="!loading">
            <template x-for="media in mediaItems" :key="media.id">
                <div 
                    @click="
                        selectedMediaId = media.id;
                        $dispatch('add-uploadcare-file', {
                            field: '{{ $getFieldName() }}',
                            cdnUrl: media.cdn_url
                        });
                    "
                    class="relative group cursor-pointer rounded-lg border-2 transition-all duration-200 hover:shadow-md"
                    :class="selectedMediaId === media.id ? 'border-blue-500 ring-2 ring-blue-200' : 'border-gray-200 hover:border-gray-300 dark:border-gray-700 dark:hover:border-gray-600'"
                >
                    <div x-show="media.is_image && media.cdn_url" class="aspect-square rounded-md overflow-hidden bg-gray-100 dark:bg-gray-800">
                        <img 
                            :src="media.cdn_url" 
                            :alt="media.filename"
                            class="w-full h-full object-cover"
                            loading="lazy"
                        />
                    </div>
                    <div x-show="!media.is_image || !media.cdn_url" class="aspect-square rounded-md bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    
                    <div class="p-2">
                        <div class="text-xs font-medium text-gray-900 dark:text-gray-100 truncate" :title="media.filename" x-text="media.filename"></div>
                        <div x-show="media.is_image && media.width && media.height" class="text-xs text-gray-500 dark:text-gray-400" x-text="media.width + '×' + media.height"></div>
                    </div>
                    
                    <div class="absolute top-1 right-1" x-show="selectedMediaId === media.id">
                        <div class="w-5 h-5 bg-blue-500 rounded-full flex items-center justify-center">
                            <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </template>
        </div>
        
        <div x-show="loading" class="flex items-center justify-center py-8">
            <div class="text-gray-500 dark:text-gray-400">{{ __('Loading...') }}</div>
        </div>
        
        <div x-show="!loading && mediaItems.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
            <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
            <p>{{ __('No media files found') }}</p>
        </div>

        <div x-show="!loading && hasPages" class="flex items-center justify-between pt-4 border-t border-gray-200 dark:border-gray-700">
            <div class="flex items-center space-x-2">
                <button 
                    type="button"
                    @click="goToPage(currentPage - 1)"
                    :disabled="onFirstPage"
                    :class="onFirstPage ? 'px-3 py-1 text-sm font-medium text-gray-400 bg-gray-100 border border-gray-300 rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-gray-500' : 'px-3 py-1 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed dark:bg-gray-800 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700'"
                >
                    {{ __('Previous') }}
                </button>
                
                <span class="text-sm text-gray-700 dark:text-gray-300">
                    {{ __('Page') }} <span x-text="currentPage"></span> {{ __('of') }} <span x-text="lastPage"></span>
                </span>
                
                <button 
                    type="button"
                    @click="goToPage(currentPage + 1)"
                    :disabled="!hasMorePages"
                    :class="!hasMorePages ? 'px-3 py-1 text-sm font-medium text-gray-400 bg-gray-100 border border-gray-300 rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-gray-500' : 'px-3 py-1 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed dark:bg-gray-800 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700'"
                >
                    {{ __('Next') }}
                </button>
            </div>
            
            <div class="flex items-center space-x-2">
                <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Per page') }}:</span>
                <select 
                    @change="changePerPage($event.target.value)"
                    class="text-sm border border-gray-300 rounded-md px-2 py-1 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300"
                >
                    <option value="6" :selected="perPage === 6">6</option>
                    <option value="12" :selected="perPage === 12">12</option>
                    <option value="24" :selected="perPage === 24">24</option>
                    <option value="48" :selected="perPage === 48">48</option>
                </select>
            </div>
        </div>
    </div>
</x-dynamic-component>