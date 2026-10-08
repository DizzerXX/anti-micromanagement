<div>
    <div class="ld-toolbar">
        <label class="ld-filter ld-filter-search">
            <x-ld.icon name="search" :size="14" />
            <span class="ld-sr-only">Filter tasks</span>
            <input type="search" placeholder="Filter tasks" wire:model.live.debounce.250ms="search" />
        </label>
        <label class="ld-filter ld-filter-select">Status
            <select wire:model.live="statusFilter">
                <option value="">All</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="ld-filter ld-filter-select">Module
            <select wire:model.live="moduleFilter">
                <option value="">All</option>
                @foreach ($modules as $module)
                    <option value="{{ $module->id }}">{{ $module->name }}</option>
                @endforeach
                <option value="none">No module</option>
            </select>
        </label>
        <label class="ld-check">
            <input type="checkbox" wire:model.live="groupByModule" />Group by module
        </label>
    </div>

    <div class="ld-table-wrap">
        <table class="ld-table">
            <thead>
                <tr>
                    <th scope="col">Task</th>
                    <th scope="col" style="width: 150px">Status</th>
                    <th scope="col" style="width: 150px">Last update</th>
                    <th scope="col" style="width: 170px">Due</th>
                    <th scope="col" style="width: 112px"><span class="ld-sr-only">Actions</span></th>
                </tr>
            </thead>

            @if ($total === 0)
                <tbody>
                    <tr class="ld-table-empty">
                        <td colspan="5">No tasks in this project yet. <button type="button" class="ld-link" style="background: none; border: 0; padding: 0; cursor: pointer" wire:click="$parent.mountAction('newTask')">New task</button></td>
                    </tr>
                </tbody>
            @elseif ($groupByModule)
                @forelse ($groups as $group)
                    @php $isCollapsed = ($collapsed[$group['key']] ?? false) && ! $filtering; @endphp
                    <tbody wire:key="group-{{ $group['key'] }}">
                        <tr class="ld-group-row">
                            <th scope="rowgroup" colspan="5">
                                <button
                                    type="button"
                                    class="ld-group-toggle"
                                    aria-expanded="{{ $isCollapsed ? 'false' : 'true' }}"
                                    wire:click="toggleGroup('{{ $group['key'] }}')"
                                >
                                    <x-ld.icon :name="$isCollapsed ? 'chevron-right' : 'chevron-down'" :size="14" />
                                    {{ $group['name'] }}
                                    <span class="ld-muted">
                                        @if ($group['total'] === 0)
                                            0 tasks
                                        @else
                                            {{ $group['done'] }} of {{ $group['total'] }} done{{ $isCollapsed ? ' · collapsed' : '' }}
                                        @endif
                                    </span>
                                </button>
                            </th>
                        </tr>
                        @unless ($isCollapsed)
                            @forelse ($group['tasks'] as $task)
                                @include('livewire.partials.task-row', ['task' => $task, 'last' => $loop->last])
                            @empty
                                <tr class="ld-table-empty ld-group-end">
                                    <td colspan="5">
                                        No tasks in this module yet.
                                        <button type="button" class="ld-link" style="background: none; border: 0; padding: 0; cursor: pointer" wire:click="$parent.mountAction('newTask', { module: {{ $group['module']?->id ?? 'null' }} })">New task</button>
                                    </td>
                                </tr>
                            @endforelse
                        @endunless
                    </tbody>
                @empty
                    <tbody>
                        <tr class="ld-table-empty"><td colspan="5">No tasks match these filters. Clear a filter to see more.</td></tr>
                    </tbody>
                @endforelse
            @else
                <tbody>
                    @forelse ($flat as $task)
                        @include('livewire.partials.task-row', ['task' => $task, 'last' => false, 'showModule' => true])
                    @empty
                        <tr class="ld-table-empty"><td colspan="5">No tasks match these filters. Clear a filter to see more.</td></tr>
                    @endforelse
                </tbody>
            @endif
        </table>
    </div>

    <x-filament-actions::modals />
</div>
