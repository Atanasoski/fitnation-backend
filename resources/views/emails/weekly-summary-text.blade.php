Hello, {!! $user->name !!}!

{!! ucfirst($workouts) !!} — {!! $workoutsDelta !!}
@if($volume)
{!! $volume !!} — {!! $volumeDelta !!}
@endif
{!! $time !!}

{!! $trendLine !!}

{!! $button !!}: {!! $appUrl !!}

{!! $unsubscribe !!}: {!! $unsubscribeUrl !!}

{!! $user->partner?->name ?? config('app.name') !!}
Powered by {!! config('app.name') !!}
