{{--
    A column chart that survives every mail client: one table, columns as cells,
    bars as fixed-height blocks. Expects $title, $bars (label, height,
    value_label, optional current) and $accent. The bar the story is about is
    the accent; the rest are context, in gray. Labels carry the reading where
    the colour alone would not.
--}}
@php($gap = 100 / max(count($bars), 1))
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin: 28px 0 0 0;">
    <tr>
        <td colspan="{{ count($bars) }}" style="padding: 0 0 12px 0; font-size: 13px; font-weight: 600; color: #111827;">{{ $title }}</td>
    </tr>
    <tr>
        @foreach($bars as $bar)
            <td width="{{ round($gap, 2) }}%" align="center" valign="bottom" style="padding: 0 3px; vertical-align: bottom; height: 96px;">
                @if($bar['value_label'] !== null)
                    <div style="font-size: 11px; line-height: 14px; color: #111827; padding-bottom: 4px; white-space: nowrap;">{{ $bar['value_label'] }}</div>
                @endif
                @if($bar['height'] > 0)
                    <div style="height: {{ $bar['height'] }}px; width: 100%; max-width: 24px; margin: 0 auto; background-color: {{ ($bar['current'] ?? true) ? $accent : '#d1d5db' }}; border-radius: 4px 4px 0 0; font-size: 0; line-height: 0;">&nbsp;</div>
                @endif
            </td>
        @endforeach
    </tr>
    <tr>
        @foreach($bars as $bar)
            <td align="center" style="padding: 6px 3px 0 3px; border-top: 1px solid #e5e7eb; font-size: 11px; line-height: 14px; color: {{ ($bar['current'] ?? false) ? '#111827' : '#6b7280' }}; white-space: nowrap;">{{ $bar['label'] }}</td>
        @endforeach
    </tr>
</table>
