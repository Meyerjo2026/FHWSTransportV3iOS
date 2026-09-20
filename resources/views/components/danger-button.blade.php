<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center px-6 py-2.5 bg-[#ff3b30] border border-transparent rounded-full font-medium text-sm text-white hover:bg-[#ff453a] active:bg-[#e63126] focus:outline-none focus:ring-2 focus:ring-[#ff3b30] focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
