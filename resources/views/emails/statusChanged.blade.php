<!DOCTYPE html>
<html>
    <h1>Dobrý deň, {{ $user->first_name }}</h1>
    <p>Stav vašej prihlášky <strong>#{{ $application->id }}</strong> bol aktualizovaný.</p>
    <p>Predchádzajúci stav: <strong>{{ $oldStatus }}</strong></p>
    <p>Nový stav: <strong>{{ $application->status }}</strong></p>
    <p>V prípade otázok kontaktujte administráciu NTI.</p>
    <hr>
    <h1>Hello, {{ $user->first_name }}</h1>
    <p>Your application <strong>#{{ $application->id }}</strong> status has been updated.</p>
    <p>Previous status: <strong>{{ $oldStatus }}</strong></p>
    <p>New status: <strong>{{ $application->status }}</strong></p>
    <p>If you have questions, contact NTI administration.</p>
</html>