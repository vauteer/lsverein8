{{--
    The digest of the errors in the log. The rows are raw HTML on purpose: a
    markdown table would break on the first pipe or asterisk in a log message,
    while inside an HTML block the escaped text is passed through untouched.
    Every block below has to stay free of blank lines, which would end the
    HTML block and hand the rest back to the markdown parser.
--}}
<x-mail::message>
# {{ $count }} error(s) in the last 24 hours

@if (count($summary) > 1)
<p>
@foreach ($summary as $badge)
<span style="display: inline-block; padding: 3px 8px; border-radius: 4px; background-color: {{ $badge['color'] }}; color: #ffffff; font-size: 11px; font-weight: bold; letter-spacing: 0.5px;">{{ $badge['level'] }}</span>&nbsp;{{ $badge['count'] }}&times;&nbsp;&nbsp;
@endforeach
</p>
@endif

Period: {{ $since->format('Y-m-d H:i') }} to {{ $until->format('Y-m-d H:i') }}.

<div class="table">
<table width="100%" cellpadding="0" cellspacing="0">
<tr>
<th align="left" style="padding-right: 16px;">Time</th>
<th align="left" style="padding-right: 16px;">Level</th>
<th align="left">Message</th>
</tr>
@foreach ($rows as $row)
<tr>
<td style="vertical-align: top; border-bottom: 1px solid #f4f4f5; white-space: nowrap; padding-right: 16px; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 13px;">{{ $row['time'] }}</td>
<td style="vertical-align: top; border-bottom: 1px solid #f4f4f5; white-space: nowrap; padding-right: 16px;"><span style="display: inline-block; padding: 3px 8px; border-radius: 4px; background-color: {{ $row['color'] }}; color: #ffffff; font-size: 11px; font-weight: bold; letter-spacing: 0.5px;">{{ $row['level'] }}</span></td>
<td style="vertical-align: top; border-bottom: 1px solid #f4f4f5; word-break: break-word; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 13px;">{{ $row['message'] }}</td>
</tr>
@endforeach
</table>
</div>

@if ($omitted > 0)
<x-mail::panel>
… and {{ $omitted }} more that are not listed here.
</x-mail::panel>
@endif

<x-mail::button :url="$dashboardUrl">
Open the dashboard
</x-mail::button>

The full messages including the stack trace are shown by `php artisan app:show-errors 1 --stack`.
</x-mail::message>
