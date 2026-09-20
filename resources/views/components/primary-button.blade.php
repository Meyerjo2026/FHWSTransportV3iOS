<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center px-6 py-2.5 bg-[#0071e3] border border-transparent rounded-full font-medium text-sm text-white hover:bg-[#0077ed] focus:bg-[#0077ed] active:bg-[#0060d5] focus:outline-none focus:ring-2 focus:ring-[#0071e3] focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
