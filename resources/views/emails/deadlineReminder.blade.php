<!DOCTYPE html>
<html>
    <h1>Dobrý deň, {{ $user->first_name }}</h1>
    <p>Pripomíname, že termín podania prihlášky sa blíži.</p>
    <p>Termín: <strong>{{ \Carbon\Carbon::parse($call->deadline_at)->format('d.m.Y H:i') }}</strong></p>
    <p>Uistite sa, že vaša prihláška je kompletná pred uplynutím termínu.</p>
    <hr>
    <h1>Hello, {{ $user->first_name }}</h1>
    <p>This is a reminder that the application deadline is approaching.</p>
    <p>Deadline: <strong>{{ \Carbon\Carbon::parse($call->deadline_at)->format('d.m.Y H:i') }}</strong></p>
    <p>Please make sure your application is complete before the deadline.</p>
</html>