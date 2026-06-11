<!DOCTYPE html>
<html>
<head>
    <title>Doplnenie prihlášky NTI</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <h2 style="color: #2563eb;">Vážený/á {{ $user->name }},</h2>
    
    <p>Oznamujeme Vám, že zmeny, ktoré ste vykonali na Vašej prihláške <strong>#{{ $application->id }}</strong> pre <strong>Program {{ strtoupper($application->program_type) }}</strong>, boli úspešne zaregistrované v systéme Nitrianskeho technologického inkubátora (NTI).</p>
    
    <p>Prihláška bola opätovne postúpená do procesu schvaľovania. O ďalšom postupe a zmene statusu Vás budeme informovať e-mailom.</p>

    <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">
    <p style="font-size: 12px; color: #777;">Tento e-mail bol vygenerovaný automaticky systémom NTI Backoffice. Prosím, neodpovedajte naň.</p>
</body>
</html>