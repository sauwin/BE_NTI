<!DOCTYPE html>
<html>
    <p>Dobrý deň {{ $user->first_name }},</p>
    <p>Požiadali ste o reset hesla. Kliknite na odkaz nižšie.</p>
    <p><a href="{{ $resetUrl }}">Resetovať heslo</a></p>
    <p>Odkaz vyprší za {{ $expiresIn }}.</p>
    <p>Ak ste o reset nepožiadali, ignorujte tento e-mail.</p>
    <hr>
    <p>Hello {{ $user->first_name }},</p>
    <p>You requested a password reset. Click the link below to reset your password.</p>
    <p><a href="{{ $resetUrl }}">Reset Password</a></p>
    <p>This link expires in {{ $expiresIn }}.</p>
    <p>If you didn't request this, ignore this email.</p>
</html>