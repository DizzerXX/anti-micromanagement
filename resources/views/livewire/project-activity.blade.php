<div>
    <section class="ld-card" aria-label="Activity">
        @if ($events->isEmpty())
            <p class="ld-card-empty">Nothing logged in this project yet. Press <x-ld.kbd>L</x-ld.kbd> to log an update.</p>
        @else
            <ol class="ld-events ld-events-dated">
                @foreach ($events as $event)
                    <x-ld.event-row
                        :event="$event"
                        :time="$event->logged_for->isToday() ? 'Today '.$event->created_at->format('H:i') : \App\Support\Dates::day($event->logged_for)"
                    />
                @endforeach
            </ol>
            @if ($more)
                <div class="ld-card-empty" style="border-top: 1px solid var(--color-border)">
                    <button type="button" class="ld-btn ld-btn-outline ld-btn-sm" wire:click="showMore">Show older updates</button>
                </div>
            @endif
        @endif
    </section>
</div>
