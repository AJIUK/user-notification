@php
use UserNotification\Support\UserNotificationAction;
use UserNotification\Support\UserNotificationLines;
@endphp

@component('mail::message')
@if (!empty($title))
# {{ $title->format() }}
@endif

@foreach ($layout->items() as $item)
@if ($item instanceof UserNotificationLines)
@if ($item->component)
@component($item->component->value)
@if($item->glue)

{!! implode("<br/>", array_map(fn($line) => nl2br($line->format()), $item->all())) !!}

@else
@foreach ($item->lines() as $line)

{!! nl2br($line->format()) !!}

@endforeach
@endif
@endcomponent
@else
@if($item->glue)

{!! implode("<br/>", array_map(fn($line) => nl2br($line->format()), $item->all())) !!}

@else
@foreach ($item->lines() as $line)

{!! nl2br($line->format()) !!}

@endforeach
@endif
@endif
@elseif ($item instanceof UserNotificationAction)
@component('mail::button', ['url' => $item->url])
{{ $item->text->format() }}
@endcomponent
@endif
@endforeach
@endcomponent
