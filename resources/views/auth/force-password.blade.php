<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose a new password — Faculty of Health and Wellness Sciences</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <h1>Choose a new password</h1>
        <p class="sub">Hi {{ $user->name }} — this account was created with a temporary password. Set your own before continuing.</p>
        @if ($errors->any())
            <div class="msg error">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ route('password.force.update') }}">
            @csrf
            <div class="field">
                <label>Temporary password</label>
                <input name="current_password" type="password" placeholder="The password you were given" required autofocus>
            </div>
            <div class="field">
                <label>New password</label>
                <input name="password" type="password" placeholder="Choose a new password" required>
            </div>
            <div class="field">
                <label>Confirm new password</label>
                <input name="password_confirmation" type="password" placeholder="Repeat new password" required>
            </div>
            <button class="btn" type="submit" style="width:100%;margin-top:6px;">Set password &amp; continue</button>
        </form>
        <form method="POST" action="{{ route('logout') }}" style="margin-top:12px;">
            @csrf
            <button type="submit" class="btn secondary" style="width:100%;">Log out instead</button>
        </form>
    </div>
</div>
</body>
</html>
