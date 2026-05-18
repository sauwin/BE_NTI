<!DOCTYPE html>
<html>
    <p>Hello {{ $user->first_name }},</p>
    <p>Your password was reset by a super administrator.</p>
    <p>Temporary password: <code>{{ $temporaryPassword }}</code></p>
    <p>Log in at: <a href="{{ $loginUrl }}">{{ $loginUrl }}</a></p>
    <p>You will be prompted to change this password on first login.</p>
</html>