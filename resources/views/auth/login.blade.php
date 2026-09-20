<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty of Health and Wellness Sciences — Transport Requests</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <h1>Faculty of Health and Wellness Sciences</h1>
        <p class="sub">Transport Request Platform</p>
        <div class="tabbar">
            <a class="active">Sign in</a>
            <a href="{{ route('register') }}">Student sign up</a>
        </div>
        @if ($errors->any())
            <div class="msg error">{{ $errors->first() }}</div>
        @endif
        @if (session('status'))
            <div class="msg success">{{ session('status') }}</div>
        @endif
        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="field">
                <label>Email</label>
                <input name="email" type="email" placeholder="you@example.com" value="{{ old('email') }}" required autofocus>
            </div>
            <div class="field">
                <label>Password</label>
                <input name="password" type="password" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;" required>
            </div>
            <button class="btn" type="submit" style="width:100%;margin-top:6px;">Sign in</button>
        </form>
        <div class="demo-creds">
            <strong>Demo accounts</strong><br>
            Student: student@mycput.ac.za / student123<br>
            Staff: staff@cput.ac.za / staff123<br>
            Admin: admin@cput.ac.za / admin123
        </div>
    </div>
</div>
</body>
</html>
