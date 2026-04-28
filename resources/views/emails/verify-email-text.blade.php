@php
    $partnerName = $user->partner?->name ?? config('app.name');
@endphp

Hello, {{ $user->name }}!

Welcome to {{ $partnerName }}.

You recently created an account at {{ $partnerName }}.
Please verify your email address to activate your account:

{{ $verificationUrl }}

If you did not create this account, you can safely ignore this email.

Powered by Fit Nation
