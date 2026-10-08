<x-filament-panels::page>
    <div class="ld-stack-24">
        <h1 class="ld-h1">Settings</h1>

        <form wire:submit="save" class="ld-card ld-card-pad" style="max-width: 560px; padding: 20px; gap: 20px">
            <h2 class="ld-card-title">Reports</h2>
            {{ $this->form }}
            <div>
                <button type="submit" class="ld-btn ld-btn-primary">Save settings</button>
            </div>
        </form>

        <section class="ld-card ld-card-pad" style="max-width: 560px; padding: 20px" aria-labelledby="statuses-h">
            <h2 id="statuses-h" class="ld-card-title">Statuses</h2>
            <p class="ld-muted" style="margin: 0">A fixed set of four. Every change is logged as an update and feeds the reports.</p>
            <div class="ld-btn-row">
                @foreach ($statuses as $status)
                    <x-ld.status-badge :status="$status" />
                @endforeach
            </div>
        </section>
    </div>
</x-filament-panels::page>
