<!DOCTYPE html>
<html>
    <h1>Dobrý deň, {{ $user->first_name }}</h1>
    <p>Kliknite na tlačidlo nižšie a overte svoju e-mailovú adresu.</p>
    <a href="{{ $verifyUrl }}" style="background:#1d4ed8;color:white;padding:12px 24px;text-decoration:none;border-radius:4px;">
        Overiť e-mail
    </a>
    <p>Odkaz vyprší za 60 minút.</p>
    <hr>
    <h1>Hello, {{ $user->first_name }}</h1>
    <p>Click the button below to verify your email address.</p>
    <a href="{{ $verifyUrl }}" style="background:#1d4ed8;color:white;padding:12px 24px;text-decoration:none;border-radius:4px;">
        Verify Email
    </a>
    <p>Link expires in 60 minutes.</p>
</html>