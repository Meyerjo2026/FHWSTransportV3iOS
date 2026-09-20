@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'font-medium text-sm text-[#248a3d]']) }}>
        {{ $status }}
    </div>
@endif
