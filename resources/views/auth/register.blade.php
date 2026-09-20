<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty of Health and Wellness Sciences — Transport Requests</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <h1>Faculty of Health and Wellness Sciences</h1>
        <p class="sub">Transport Request Platform</p>
        <div class="tabbar">
            <a href="{{ route('login') }}">Sign in</a>
            <a class="active">Student sign up</a>
        </div>
        @if ($errors->any())
            <div class="msg error">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ route('register') }}">
            @csrf
            <div class="field">
                <label>Full name</label>
                <input name="name" placeholder="Thandi Nkosi" value="{{ old('name') }}" required autofocus>
            </div>
            <div class="field">
                <label>Student email</label>
                <input name="email" type="email" placeholder="you@mycput.ac.za" value="{{ old('email') }}" required>
            </div>
            <div class="field">
                <label>Student / contact number</label>
                <input name="number" placeholder="082 123 4567" value="{{ old('number') }}" required>
            </div>
            <div class="field">
                <label>Password</label>
                <input name="password" type="password" placeholder="Choose a password" required>
            </div>
            <div class="field">
                <label>Confirm password</label>
                <input name="password_confirmation" type="password" placeholder="Repeat password" required>
            </div>
            <button class="btn" type="submit" style="width:100%;margin-top:6px;">Create student account</button>
        </form>
        <div class="hint">Staff and administrator accounts are created by the administrator, not through self sign-up.</div>
    </div>
</div>
</body>
</html>
