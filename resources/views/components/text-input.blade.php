@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-black/10 focus:border-[#0071e3] focus:ring-[#0071e3] rounded-[12px] shadow-sm py-2.5']) }}>
