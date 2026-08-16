@props(['active'])

@php
$classes = ($active ?? false)
            ? 'flex items-center gap-3 w-full px-4 py-3 rounded-xl text-left text-base font-bold text-[var(--color-primary)] bg-[var(--color-primary)]/10 focus:outline-none transition duration-150 ease-in-out'
            : 'flex items-center gap-3 w-full px-4 py-3 rounded-xl text-left text-base font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800/50 focus:outline-none transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
