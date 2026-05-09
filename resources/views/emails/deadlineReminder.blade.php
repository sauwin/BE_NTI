<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <h1>Hello, {{ $user->first_name }}</h1>
    <p>This is a reminder that the application deadline is approaching.</p>
    <p>Deadline: <strong>{{ \Carbon\Carbon::parse($call->deadline_at)->format('d.m.Y H:i') }}</strong></p>
    <p>Please make sure your application is complete before the deadline.</p>
</html>