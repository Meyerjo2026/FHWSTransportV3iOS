<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center px-6 py-2.5 bg-[#993399] border border-transparent rounded-full font-medium text-sm text-white hover:bg-[#a44ca4] focus:bg-[#a44ca4] active:bg-[#802b80] focus:outline-none focus:ring-2 focus:ring-[#993399] focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
