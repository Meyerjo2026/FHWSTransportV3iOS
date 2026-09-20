<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Faculty of Health and Wellness Sciences — Transport Requests' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght;500;600;700&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body>
<header class="topbar">
    <div>
        <h1>Faculty of Health and Wellness Sciences</h1>
        <div class="sub">Transport Request Platform</div>
    </div>
    <div class="userbox">
        <div>{{ $user->name }} <span class="pill {{ $user->role }}">{{ $user->role }}</span></div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit">Log out</button>
        </form>
    </div>
</header>
<nav class="tabs">
    @foreach ($tabs as $href => $label)
        <a href="{{ $href }}" class="{{ $href === $active ? 'active' : '' }}">{{ $label }}</a>
    @endforeach
</nav>
<main class="content">
    @if (session('success'))
        <div class="msg success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="msg error">{{ session('error') }}</div>
    @endif
    {{ $slot }}
</main>
</body>
</html>
