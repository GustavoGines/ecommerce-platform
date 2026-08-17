<div x-data="{ open: @entangle('showImportModal').live }">
    <!-- Botón para abrir el modal -->
    <button @click="open = true" class="group shrink-0 flex items-center justify-center p-2.5 rounded-full transition-all hover:bg-green-50 dark:hover:bg-green-900/30 border border-green-200 dark:border-green-700/50 shadow-sm text-green-700 dark:text-green-400 font-bold ml-2">
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
        <div class="grid grid-cols-[0fr] group-hover:grid-cols-[1fr] transition-all duration-300 ease-in-out">
            <span class="overflow-hidden whitespace-nowrap text-sm"><span class="pl-2 pr-1">Importar Excel</span></span>
        </div>
    </button>

    <!-- Modal -->
    <div x-show="open" class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true" x-cloak>
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <!-- Overlay -->
            <div x-show="open" 
                 x-transition:enter="ease-out duration-300" 
                 x-transition:enter-start="opacity-0" 
                 x-transition:enter-end="opacity-100" 
                 x-transition:leave="ease-in duration-200" 
                 x-transition:leave-start="opacity-100" 
                 x-transition:leave-end="opacity-0" 
                 class="fixed inset-0 bg-gray-900/40 dark:bg-[#0b0f19]/80 backdrop-blur-sm transition-opacity" aria-hidden="true" @click="open = false"></div>
            
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            
            <!-- Contenido del Modal -->
            <div x-show="open" 
                 x-transition:enter="ease-out duration-300" 
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
                 x-transition:leave="ease-in duration-200" 
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" 
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
                 class="inline-block bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-3xl text-left shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full max-h-[90vh] overflow-y-auto relative w-full">
                
                <div class="px-8 pt-8 pb-4">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-2xl leading-6 font-bold text-gray-900 dark:text-white tracking-tight">
                            Importar Productos
                        </h3>
                        <button @click="open = false" class="text-gray-400 hover:text-gray-500 transition-colors">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <div class="mb-6 bg-blue-50 dark:bg-blue-900/20 text-blue-800 dark:text-blue-300 p-4 rounded-xl text-sm border border-blue-100 dark:border-blue-800/30">
                        <p class="font-bold mb-1">Estructura requerida del archivo (.xlsx, .csv):</p>
                        <ul class="list-disc pl-5 space-y-1 opacity-90">
                            <li><strong>Nombre</strong>: Nombre del producto.</li>
                            <li><strong>SKU</strong> o <strong>Codigo</strong>: Identificador único.</li>
                            <li><strong>Precio</strong>: Precio de lista final.</li>
                            <li><strong>Precio_Mayorista</strong>: Precio al por mayor.</li>
                            <li><strong>Costo</strong> <span class="text-xs font-normal text-blue-600 dark:text-blue-400 bg-blue-100 dark:bg-blue-900/30 px-1.5 py-0.5 rounded ml-1">Opcional</span>: Costo de compra del producto.</li>
                            <li><strong>Stock</strong>: Cantidad disponible.</li>
                            <li><strong>Categoria</strong>: Nombre de la categoría (se crea si no existe).</li>
                            <li><strong>Marca</strong> <span class="text-xs font-normal text-blue-600 dark:text-blue-400 bg-blue-100 dark:bg-blue-900/30 px-1.5 py-0.5 rounded ml-1">Opcional</span>: Si la omites, el sistema intentará detectarla inteligentemente leyendo el nombre del producto (ej: Samsung, LG).</li>
                        </ul>
                    </div>

                    <form wire:submit="import">
                        <div class="mb-6">
                            <!-- Contenedor Principal Drag & Drop -->
                            <div x-data="{ isDropping: false }" class="relative">
                                
                                <!-- Capturador inicial (detecta cuando el archivo entra por primera vez) -->
                                <div @dragenter.prevent="isDropping = true" @dragover.prevent class="absolute inset-0 z-0 rounded-xl"></div>

                                <!-- Diseño Normal del Dropzone -->
                                <div :class="{ 'border-transparent shadow-lg scale-105': isDropping, 'border-gray-300 dark:border-gray-700': !isDropping }"
                                     class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-dashed rounded-xl relative hover:border-[var(--color-primary)] dark:hover:border-[var(--color-primary)] transition-all duration-300 group bg-white dark:bg-transparent pointer-events-none">
                                    
                                    <div class="space-y-1 text-center">
                                        <svg class="mx-auto h-12 w-12 text-gray-400 group-hover:text-[var(--color-primary)] transition-colors" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        <div class="flex text-sm text-gray-600 dark:text-gray-400 justify-center">
                                            <label for="file-upload" class="relative cursor-pointer font-medium text-[var(--color-primary)] hover:text-red-500 focus-within:outline-none pointer-events-auto">
                                                <span>Seleccionar un archivo</span>
                                                <input id="file-upload" x-ref="fileInput" wire:model="file" type="file" class="sr-only" accept=".xlsx,.xls,.csv">
                                            </label>
                                            <p class="pl-1">o arrastrar aquí</p>
                                        </div>
                                        <p class="text-xs text-gray-500">Excel (.xlsx) o CSV hasta 10MB</p>
                                    </div>
                                </div>

                                <!-- Overlay Activo (Atrapa todos los eventos mientras se arrastra) -->
                                <div x-show="isDropping" 
                                     x-transition
                                     @dragover.prevent
                                     @dragleave.prevent="isDropping = false"
                                     @drop.prevent="isDropping = false; if($event.dataTransfer.files.length > 0) { $refs.fileInput.files = $event.dataTransfer.files; $refs.fileInput.dispatchEvent(new Event('change', { bubbles: true })); }"
                                     class="absolute inset-0 z-50 flex flex-col items-center justify-center rounded-xl bg-white/95 dark:bg-gray-900/95 backdrop-blur-sm border-2 border-[var(--color-primary)] shadow-lg scale-105 transition-all duration-300 cursor-copy">
                                    <svg class="h-12 w-12 text-[var(--color-primary)] animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                    </svg>
                                    <span class="text-lg font-bold text-[var(--color-primary)] mt-2">¡Suelta el archivo aquí!</span>
                                </div>
                            </div>
                            @if ($file)
                                <div class="mt-3 text-sm text-green-600 dark:text-green-400 font-medium text-center">
                                    Archivo seleccionado: {{ $file->getClientOriginalName() }}
                                </div>
                            @endif
                            @error('file') <span class="text-red-500 text-xs mt-1 block text-center">{{ $message }}</span> @enderror
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-800/50 px-4 py-4 sm:px-6 sm:flex sm:flex-row-reverse rounded-b-3xl -mx-8 -mb-4 mt-6 border-t border-gray-200 dark:border-gray-700">
                            <button type="submit" class="w-full inline-flex justify-center rounded-xl border border-transparent shadow-sm px-6 py-2.5 bg-[var(--color-primary)] text-base font-bold text-white hover:bg-red-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm transition-all" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="import">Procesar Importación</span>
                                <span wire:loading.flex wire:target="import" class="flex items-center justify-center">
                                    <svg class="animate-spin -ml-1 mr-2 h-5 w-5 text-white shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    <span>Importando...</span>
                                </span>
                            </button>
                            <button type="button" @click="open = false" class="mt-3 w-full inline-flex justify-center rounded-xl border border-gray-300 dark:border-gray-600 shadow-sm px-6 py-2.5 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm transition-colors">
                                Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
