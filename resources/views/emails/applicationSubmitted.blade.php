<!DOCTYPE html>
<html>
    <h1>Dobrý deň, {{ $user->first_name }}</h1>
    <p>Vaša prihláška do <strong>Programu {{ strtoupper($application->program_type) }}</strong> bola úspešne odoslaná.</p>
    <p>ID prihlášky: <strong>#{{ $application->id }}</strong></p>
    <p>Aktuálny stav: <strong>{{ $application->status }}</strong></p>
    <p>Administrácia NTI posúdi vašu prihlášku a informuje vás o rozhodnutí.</p>
    <hr>
    <h1>Hello, {{ $user->first_name }}</h1>
    <p>Your application for <strong>Program {{ strtoupper($application->program_type) }}</strong> has been successfully submitted.</p>
    <p>Application ID: <strong>#{{ $application->id }}</strong></p>
    <p>Current status: <strong>{{ $application->status }}</strong></p>
    <p>The NTI admin will review your application and notify you about the decision.</p>
</html>