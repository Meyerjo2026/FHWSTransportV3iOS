@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-black/10 focus:border-[#993399] focus:ring-[#993399] rounded-[12px] shadow-sm py-2.5']) }}>
