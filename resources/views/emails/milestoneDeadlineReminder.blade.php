<!DOCTYPE html>
<html>
    <h1>Dobrý deň, {{ $user->first_name }}</h1>
    <p>Pripomíname, že míľnik <strong>{{ $milestone->name }}</strong> má termín <strong>{{ $milestone->due_date->format('d.m.Y') }}</strong>.</p>
    <p>Aktuálny stav: <strong>{{ $milestone->status }}</strong></p>
    <hr>
    <h1>Hello, {{ $user->first_name }}</h1>
    <p>This is a reminder that milestone <strong>{{ $milestone->name }}</strong> is due on <strong>{{ $milestone->due_date->format('d.m.Y') }}</strong>.</p>
    <p>Current status: <strong>{{ $milestone->status }}</strong></p>
</html>