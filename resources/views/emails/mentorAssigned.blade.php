<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <h1>Hello, {{ $student->first_name }}</h1>
    <p>A mentor has been assigned to your project.</p>
    <p>Your mentor: <strong>{{ $mentor->first_name }} {{ $mentor->last_name }}</strong></p>
    <p>They will contact you shortly to schedule your first consultation.</p>
</html>