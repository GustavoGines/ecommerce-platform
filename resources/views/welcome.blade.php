@php
    $theme = app('activeTheme') ?? 'modern-light';
    if (!view()->exists('themes.' . $theme . '.welcome')) {
        $theme = 'modern-light';
    }
@endphp

@include('themes.' . $theme . '.welcome')
