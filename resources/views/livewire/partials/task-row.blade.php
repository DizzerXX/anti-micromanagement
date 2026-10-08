@php
    use App\Enums\TaskStatus;
    use App\Support\Dates;

    /** @var \App\Models\Task $task */
    $update = $task->latestUpdate;
    $staleFor = $task->current_status === TaskStatus::InProgress
        ? (int) $task->last_logged_for->diffInDays($today)
        : null;
    $isStale = $staleFor !== null && $staleFor >= $staleDays;
    $lateBy = $task->due_date && $task->current_status !== TaskStatus::Done && $task->due_date->lt($today)
        ? (int) $task->due_date->diffInDays($today)
        : null;
@endphp

<tr wire:key="task-{{ $task->id }}" @class(['ld-group-end' => $last])>
    <td>
        <div class="ld-stack-2">
            <span class="ld-strong">{{ $task->title }}</span>
            @if (! empty($showModule) && $task->module)
                <span class="ld-path">{{ $task->module->name }}</span>
            @endif
            @if ($task->current_status === TaskStatus::Blocked && filled($task->latestBlock?->note))
                <span class="ld-blocker-line" title="{{ $task->latestBlock->note }}">{{ $task->latestBlock->note }}</span>
            @endif
        </div>
    </td>
    <td><x-ld.status-badge :status="$task->current_status" /></td>
    <td class="ld-cell-date">
        @if ($isStale)
            <span class="ld-cell-stale"><x-ld.icon name="note" :size="14" />{{ Dates::ago($staleFor) }}</span>
        @elseif ($update)
            {{ Dates::lastUpdate($update->logged_for, $update->created_at, $today) }}
        @else
            <span class="ld-cell-none">—</span>
        @endif
    </td>
    <td class="ld-cell-date">
        @if ($lateBy !== null)
            <span class="ld-cell-late">{{ Dates::day($task->due_date) }} · {{ $lateBy }}d late</span>
        @elseif ($task->due_date && $task->current_status !== TaskStatus::Done)
            {{ Dates::day($task->due_date) }}
        @else
            <span class="ld-cell-none">—</span>
        @endif
    </td>
    <td class="ld-td-actions">
        <div class="ld-row-actions">
            <button
                type="button"
                class="ld-btn ld-btn-outline ld-btn-sm"
                aria-label="Log update for {{ $task->title }}"
                x-on:click="$dispatch('open-log-update', { taskId: {{ $task->id }} })"
            >Log</button>
            <x-filament::dropdown placement="bottom-end" teleport>
                <x-slot name="trigger">
                    <button type="button" class="ld-icon-btn" aria-label="More actions for {{ $task->title }}">
                        <x-ld.icon name="more" :size="16" />
                    </button>
                </x-slot>
                <x-filament::dropdown.list>
                    <x-filament::dropdown.list.item icon="ledger-pencil" wire:click="mountAction('editTask', { task: {{ $task->id }} })">Edit</x-filament::dropdown.list.item>
                    <x-filament::dropdown.list.item icon="ledger-move" wire:click="mountAction('moveTask', { task: {{ $task->id }} })">Move to module</x-filament::dropdown.list.item>
                    <x-filament::dropdown.list.item icon="ledger-trash" wire:click="mountAction('deleteTask', { task: {{ $task->id }} })">Delete</x-filament::dropdown.list.item>
                </x-filament::dropdown.list>
            </x-filament::dropdown>
        </div>
    </td>
</tr>
