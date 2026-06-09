<!DOCTYPE html>
<html>
    <h1>Dobrý deň, {{ $user->first_name }}</h1>
    <p>Stav míľnika <strong>{{ $milestone->name }}</strong> bol aktualizovaný na <strong>{{ $milestone->status }}</strong>.</p>
    @if($milestone->due_date)
        <p>Termín: <strong>{{ $milestone->due_date->format('d.m.Y') }}</strong></p>
    @endif
    <hr>
    <h1>Hello, {{ $user->first_name }}</h1>
    <p>The status of milestone <strong>{{ $milestone->name }}</strong> has been updated to <strong>{{ $milestone->status }}</strong>.</p>
    @if($milestone->due_date)
        <p>Due date: <strong>{{ $milestone->due_date->format('d.m.Y') }}</strong></p>
    @endif
</html>