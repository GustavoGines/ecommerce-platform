@props(['url'])
@php
    $settings = \App\Models\StoreSetting::getSettings();
    $logoUrl = $settings && $settings->logo_url ? tenant_asset($settings->logo_url) : null;
@endphp
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if ($logoUrl)
<img src="{{ $logoUrl }}" class="logo" alt="{{ strip_tags($slot) }}">
@else
<span style="color: #ffffff; font-size: 24px; font-weight: bold;">{!! $slot !!}</span>
@endif
</a>
</td>
</tr>
