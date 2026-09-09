Hello, {!! $user->name !!}!

{!! ucfirst($workouts) !!} — {!! $workoutsDelta !!}
@if($volume)
{!! $volume !!} — {!! $volumeDelta !!}
@endif
{!! $time !!}

{!! $trendLine !!}
@if($charts)

{!! $chartWeeksTitle !!}: {!! $weeksLine !!}
@if($daysLine)
{!! $chartDaysTitle !!}: {!! $daysLine !!}
@endif
@endif

{!! $button !!}: {!! $appUrl !!}

{!! $unsubscribe !!}: {!! $unsubscribeUrl !!}

{!! $user->partner?->name ?? config('app.name') !!}
Powered by {!! config('app.name') !!}
