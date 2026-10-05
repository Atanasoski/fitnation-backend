{{-- A workout's day: Monday 0 to Sunday 6, or any day. Expects $value (int or null). --}}
<select id="workout-day" name="day_of_week" class="{{ $input }}">
    <option value="" @selected($value === null)>Any day</option>
    @foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $index => $name)
        <option value="{{ $index }}" @selected($value === $index)>{{ $name }}</option>
    @endforeach
</select>
