{{-- One vCard per member. Ported from lsverein7; the members arrive as models
     rather than arrays, so the fields are read as properties. --}}
@foreach ($members as $member)
BEGIN:VCARD
VERSION:3.0
PRODID:-//{{ $clubName }}//DE
N:{{ $member->surname }};{{ $member->first_name }};;;
FN:{{ $member->first_name }} {{ $member->surname }}
@if ($member->email)
EMAIL;type=INTERNET;type=HOME;type=pref:{{ $member->email }}
@endif
@if ($member->phone)
TEL;type=CELL;type=VOICE;type=pref:{{ $member->phone }}
@endif
BDAY:{{ $member->birthday->format('Y-m-d') }}
ADR;TYPE=home:;;{{ $member->street }};{{ $member->city }};;{{ $member->zipcode }};{{ $country }}
END:VCARD
@endforeach
