<x-mail::layout>
{{-- Inject primary color override when white-label branding is active --}}
@if (!empty($primaryColor))
<x-slot:head>
<style>
.button-blue, .button-primary {
    background-color: {{ $primaryColor }} !important;
    border-bottom-color: {{ $primaryColor }} !important;
    border-left-color: {{ $primaryColor }} !important;
    border-right-color: {{ $primaryColor }} !important;
    border-top-color: {{ $primaryColor }} !important;
}
</style>
</x-slot:head>
@endif

{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
@if (!empty($logoUrl))
<img src="{{ $logoUrl }}" alt="{{ config('app.name') }}" style="height:48px;width:auto;max-width:160px;object-fit:contain;display:block;margin:0 auto;" />
@else
{{ config('app.name') }}
@endif
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
