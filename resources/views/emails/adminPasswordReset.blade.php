<!DOCTYPE html>
<html>
  <p>Dobrý deň {{ $user->first_name }},</p>
  <p>Vaše heslo bolo resetované super administrátorom.</p>
  <p>Dočasné heslo: <code>{{ $temporaryPassword }}</code></p>
  <p>Prihláste sa na: <a href="{{ $loginUrl }}">{{ $loginUrl }}</a></p>
  <p>Pri prvom prihlásení budete vyzvaní na zmenu hesla.</p>
  <hr>
  <p>Hello {{ $user->first_name }},</p>
  <p>Your password was reset by a super administrator.</p>
  <p>Temporary password: <code>{{ $temporaryPassword }}</code></p>
  <p>Log in at: <a href="{{ $loginUrl }}">{{ $loginUrl }}</a></p>
  <p>You will be prompted to change this password on first login.</p>
</html>