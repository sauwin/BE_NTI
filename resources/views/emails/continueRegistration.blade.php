<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <h1>Hello, {{ $user->first_name }}</h1>
    <p>Click the button below to verify your email address.</p>
    <a href="{{ $verifyUrl }}" style="background:#1d4ed8;color:white;padding:12px 24px;text-decoration:none;border-radius:4px;">
        Verify Email
    </a>
    <p>Link expires in 60 minutes.</p>
</html>