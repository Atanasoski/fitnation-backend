Hello, {!! $user->name !!}!

{!! $lead !!}

{!! $button !!}: {!! $primaryUrl !!}
@if($secondary)

{!! $secondary !!}: {!! $appUrl !!}
@endif

If you did not create this account, you can safely ignore this email.

{!! $user->partner?->name ?? config('app.name') !!}
Powered by Fit Nation
