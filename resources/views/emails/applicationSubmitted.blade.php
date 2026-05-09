<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <h1>Hello, {{ $user->first_name }}</h1>
    <p>Your application for <strong>Program {{ strtoupper($application->program_type) }}</strong> has been successfully submitted.</p>
    <p>Application ID: <strong>#{{ $application->id }}</strong></p>
    <p>Current status: <strong>{{ $application->status }}</strong></p>
    4<p>The NTI committee will review your application and notify you about the decision.</p>
</html>