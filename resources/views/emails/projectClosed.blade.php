<!DOCTYPE html>
<html>
    <h1>Dobrý deň, {{ $user->first_name }}</h1>
    <p>Váš projekt <strong>#{{ $application->id }}</strong> bol uzavretý.</p>
    <p>Ďakujeme za účasť v programe NTI.</p>
    <p>Tím NTI vás bude kontaktovať ohľadom ďalších krokov a výsledkov.</p>
    <hr>
    <h1>Hello, {{ $user->first_name }}</h1>
    <p>Your project <strong>#{{ $application->id }}</strong> has been closed.</p>
    <p>Thank you for participating in the NTI program.</p>
    <p>The NTI team will be in touch regarding next steps and results.</p>
</html>