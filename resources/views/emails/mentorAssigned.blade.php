<!DOCTYPE html>
<html>
    <h1>Dobrý deň, {{ $student->first_name }}</h1>
    <p>K vášmu projektu bol priradený mentor.</p>
    <p>Váš mentor: <strong>{{ $mentor->first_name }} {{ $mentor->last_name }}</strong></p>
    <p>Čoskoro vás kontaktuje ohľadom prvej konzultácie.</p>
    <hr>
    <h1>Hello, {{ $student->first_name }}</h1>
    <p>A mentor has been assigned to your project.</p>
    <p>Your mentor: <strong>{{ $mentor->first_name }} {{ $mentor->last_name }}</strong></p>
    <p>They will contact you shortly to schedule your first consultation.</p>
</html>