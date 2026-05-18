<!DOCTYPE html>
<html>
    <p>Hello {{ $user->first_name }},</p>
    <p>You requested a password reset. Click the link below to reset your password.</p>
    <p><a href="{{ $resetUrl }}">Reset Password</a></p>
    <p>This link expires in {{ $expiresIn }}.</p>
    <p>If you didn't request this, ignore this email.</p>
</html>