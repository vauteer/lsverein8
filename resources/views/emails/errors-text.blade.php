{{--
    The text part of the digest. The messages are echoed unescaped: this part
    is read as plain text, where HTML entities would only be noise.
--}}
{{ $count }} error(s) in the last 24 hours
Period: {{ $since->format('Y-m-d H:i') }} to {{ $until->format('Y-m-d H:i') }}.

@foreach ($rows as $row)
{{ $row['time'] }} {{ $row['level'] }} {!! $row['message'] !!}
@endforeach
@if ($omitted > 0)
… and {{ $omitted }} more that are not listed here.
@endif

The full messages including the stack trace are shown by: php artisan app:show-errors 1 --stack
{{ $dashboardUrl }}
