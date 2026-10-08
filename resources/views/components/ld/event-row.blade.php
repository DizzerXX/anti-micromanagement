@props(['event', 'time', 'showPath' => true])

@php
    /** @var \App\Models\TaskEvent $event */
@endphp

<li class="ld-event">
    <span class="ld-event-time">{{ $time }}</span>
    <div class="ld-event-body">
        <div class="ld-event-title">
            <span class="ld-strong">{{ $event->task->title }}</span>
            @if ($showPath)
                <span class="ld-path">{{ $event->task->path() }}</span>
            @endif
        </div>
        <div class="ld-event-change">
            @if ($event->isProgressNote())
                <x-ld.status-badge :status="$event->to_status" />
                <span class="ld-note-tag">Note</span>
            @elseif ($event->isCreation())
                <span class="ld-note-tag">Created</span>
                <x-ld.status-badge :status="$event->to_status" />
            @else
                <x-ld.status-badge :status="$event->from_status" />
                <x-ld.icon name="arrow-right" :size="16" class="ld-arrow" label="to" />
                <x-ld.status-badge :status="$event->to_status" />
            @endif
        </div>
        @if (filled($event->note))
            <p class="ld-event-note">{{ $event->note }}</p>
        @endif
    </div>
</li>
