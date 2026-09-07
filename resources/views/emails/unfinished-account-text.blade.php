Hello, {!! $user->name !!}!

{!! $lead !!}
{!! $follow !!}

{!! $button !!}: {!! $primaryUrl !!}
@if($secondary)

{!! $secondary !!}: {!! $appUrl !!}
@endif

If you did not create this account, you can safely ignore this email.

{!! $user->partner?->name ?? config('app.name') !!}
Powered by {!! config('app.name') !!}
