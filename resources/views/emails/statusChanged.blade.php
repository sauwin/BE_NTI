<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <h1>Hello, {{ $user->first_name }}</h1>
    <p>Your application <strong>#{{ $application->id }}</strong> status has been updated.</p>
    <p>Previous status: <strong>{{ $oldStatus }}</strong></p>
    <p>New status: <strong>{{ $application->status }}</strong></p>
    <p>If you have questions, contact NTI administration.</p>
</html>