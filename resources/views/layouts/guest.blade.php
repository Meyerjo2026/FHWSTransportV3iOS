<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;500;600;700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased bg-[#f5f5f7]">
        <div class="min-h-screen flex flex-col sm:justify-center items-center px-6 py-10">
            <div class="w-full sm:max-w-md bg-white rounded-2xl border border-black/5 shadow-[0_20px_60px_rgba(0,0,0,0.10),0_2px_8px_rgba(0,0,0,0.04)] overflow-hidden p-8">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
