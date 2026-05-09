<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <h1>Hello, {{ $user->first_name }}</h1>
    <p>Your project <strong>#{{ $application->id }}</strong> has been closed.</p>
    <p>Thank you for participating in the NTI program.</p>
    <p>The NTI team will be in touch regarding next steps and results.</p>
</html>