@php
    use App\Enums\TaskStatus;

    $current = $this->currentStatus;
    $selected = TaskStatus::tryFrom((string) $status);
    $isProgressNote = $current !== null && $selected === $current;
    $noteRequired = $selected !== null && $current !== null && $selected->requiresNoteFrom($current);
    $noteHelp = match (true) {
        $selected === TaskStatus::Blocked && ! $isProgressNote => 'Appears under Blocked in the report. Say what you are waiting on.',
        $isProgressNote => 'Same status as now, so this is a progress note. Say what moved.',
        default => 'Optional. Shows under Logged today and in the project’s activity.',
    };
    $task = $this->task;
@endphp

<div
    x-data="{
        listOpen: false,
        active: 0,
        count: 0,
        focusField(which) {
            this.$nextTick(() => {
                const el = which === 'status'
                    ? (this.$refs.panel.querySelector('input[name=status]:checked') ?? this.$refs.panel.querySelector('input[name=status]'))
                    : this.$refs.taskInput
                el?.focus()
            })
        },
        firstError() {
            this.$nextTick(() => this.$refs.panel?.querySelector('[aria-invalid=true]')?.focus())
        },
    }"
    x-on:log-update-opened.window="count = 0; listOpen = false; focusField($event.detail.focus)"
    x-on:log-update-error.window="firstError()"
    x-on:keydown.escape.window="if ($wire.isOpen) { listOpen ? (listOpen = false) : $wire.close() }"
    x-on:keydown.window.ctrl.enter="if ($wire.isOpen) { $event.preventDefault(); $wire.save() }"
    x-on:keydown.window.meta.enter="if ($wire.isOpen) { $event.preventDefault(); $wire.save() }"
>
    @if ($isOpen)
            <div class="ld-slideover-scrim" x-on:click="$wire.close()"></div>

            <form
                x-ref="panel"
                x-trap.noscroll="true"
                wire:submit="save"
                role="dialog"
                aria-modal="true"
                aria-labelledby="log-title"
                aria-describedby="log-desc"
                class="ld-slideover"
            >
                <div class="ld-slideover-head">
                    <div class="ld-stack-2">
                        <h2 id="log-title" class="ld-slideover-title">Log update</h2>
                        <span id="log-desc" class="ld-path">Adds one entry to the task’s history</span>
                    </div>
                    <button type="button" class="ld-icon-btn ld-icon-btn-lg" aria-label="Close" x-on:click="$wire.close()">
                        <x-ld.icon name="close" :size="18" />
                    </button>
                </div>

                <div class="ld-slideover-body">
                    {{-- Task --}}
                    <div class="ld-field">
                        <label for="log-task" class="ld-label">Task</label>
                        <span id="log-task-help" class="ld-help">Recent tasks first. Type to search all tasks.</span>

                        <div
                            class="ld-combobox"
                            @error('taskId') aria-invalid="true" @enderror
                            x-on:click.outside="listOpen = false"
                        >
                            <div class="ld-stack-2" style="flex: 1; min-width: 0">
                                <input
                                    id="log-task"
                                    x-ref="taskInput"
                                    type="text"
                                    role="combobox"
                                    autocomplete="off"
                                    aria-autocomplete="list"
                                    aria-controls="log-task-list"
                                    x-bind:aria-expanded="listOpen"
                                    aria-describedby="log-task-help @error('taskId') log-task-error @enderror"
                                    @error('taskId') aria-invalid="true" @enderror
                                    placeholder="Search tasks"
                                    wire:model.live.debounce.200ms="taskSearch"
                                    x-on:focus="listOpen = true; active = 0; $el.select()"
                                    x-on:input="listOpen = true; active = 0"
                                    x-on:keydown.down.prevent="listOpen = true; active = Math.min(active + 1, $refs.list ? $refs.list.children.length - 1 : 0)"
                                    x-on:keydown.up.prevent="active = Math.max(active - 1, 0)"
                                    x-on:keydown.enter="if (listOpen && $refs.list?.children[active]) { $event.preventDefault(); $refs.list.children[active].click() }"
                                    x-on:keydown.tab="listOpen = false"
                                />
                                @if ($task)
                                    <span class="ld-path">{{ $task->path() }}</span>
                                @endif
                            </div>
                            <x-ld.icon name="chevron-down" :size="16" class="ld-secondary" />

                            <ul
                                id="log-task-list"
                                x-ref="list"
                                role="listbox"
                                aria-label="Tasks"
                                class="ld-combobox-list"
                                x-show="listOpen"
                                x-cloak
                            >
                                @forelse ($this->options as $i => $option)
                                    <li
                                        role="option"
                                        wire:key="opt-{{ $option->id }}"
                                        class="ld-combobox-option"
                                        x-bind:aria-selected="active === {{ $i }}"
                                        x-on:mouseenter="active = {{ $i }}"
                                        x-on:click="listOpen = false; $wire.selectTask({{ $option->id }}).then(() => focusField('status'))"
                                    >
                                        <span class="ld-strong">{{ $option->title }}</span>
                                        <span class="ld-path">{{ $option->path() }}</span>
                                    </li>
                                @empty
                                    <li class="ld-combobox-option ld-muted" role="option" aria-disabled="true">No tasks match. Try fewer words.</li>
                                @endforelse
                            </ul>
                        </div>
                        @error('taskId') <span id="log-task-error" class="ld-error">{{ $message }}</span> @enderror
                    </div>

                    {{-- Status --}}
                    <fieldset class="ld-fieldset" style="gap: 8px" aria-describedby="@error('status') log-status-error @enderror">
                        <legend class="ld-legend-title ld-spread" style="width: 100%; margin-bottom: 8px">
                            Status
                            @if ($current)
                                <span class="ld-path" style="font-weight: 400">
                                    {{ $isToday ? 'Currently' : 'On '.$loggedForLabel.':' }} {{ $current->label() }}
                                </span>
                            @endif
                        </legend>
                        <div class="ld-tiles">
                            @foreach ($statuses as $option)
                                <label class="ld-tile ld-tile-{{ $option->color() }} ld-tint-{{ $option->color() }}">
                                    <input
                                        type="radio"
                                        name="status"
                                        value="{{ $option->value }}"
                                        wire:model.live="status"
                                        @error('status') aria-invalid="true" @enderror
                                    />
                                    <x-ld.icon :name="$option->icon()" :size="16" />
                                    <span>{{ $option->label() }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('status') <span id="log-status-error" class="ld-error">{{ $message }}</span> @enderror
                    </fieldset>

                    {{-- Note --}}
                    <div class="ld-field">
                        <div class="ld-field-head">
                            <label for="log-note" class="ld-label">Note</label>
                            @if ($noteRequired)
                                <span class="ld-required-hint">{{ $isProgressNote ? 'Required for a progress note' : 'Required when Blocked' }}</span>
                            @endif
                        </div>
                        <span id="log-note-help" class="ld-help">{{ $noteHelp }}</span>
                        <textarea
                            id="log-note"
                            rows="4"
                            class="ld-textarea"
                            maxlength="{{ \App\Services\TaskStatusService::NOTE_MAX }}"
                            aria-describedby="log-note-help log-note-count @error('note') log-note-error @enderror"
                            @if ($noteRequired) aria-required="true" @endif
                            @error('note') aria-invalid="true" @enderror
                            wire:model="note"
                            x-init="count = $el.value.length"
                            x-on:input="count = $event.target.value.length"
                        ></textarea>
                        @error('note') <span id="log-note-error" class="ld-error">{{ $message }}</span> @enderror
                        <span id="log-note-count" class="ld-counter" aria-live="polite"><span x-text="count">0</span> / {{ \App\Services\TaskStatusService::NOTE_MAX }}</span>
                    </div>

                    {{-- Logged for --}}
                    <div class="ld-field">
                        <label for="log-date" class="ld-label">Logged for</label>
                        <span id="log-date-help" class="ld-help">Backdate it if you forgot to log on the day. Reports use this date.</span>
                        <input
                            id="log-date"
                            type="date"
                            class="ld-input ld-date"
                            max="{{ $today }}"
                            aria-describedby="log-date-help @error('loggedFor') log-date-error @enderror"
                            @error('loggedFor') aria-invalid="true" @enderror
                            wire:model.live="loggedFor"
                        />
                        @error('loggedFor') <span id="log-date-error" class="ld-error">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="ld-slideover-foot">
                    <button type="button" class="ld-btn ld-btn-plain" x-on:click="$wire.close()">Cancel</button>
                    <div class="ld-btn-row">
                        <button type="button" class="ld-btn ld-btn-outline" wire:click="save(true)" wire:loading.attr="disabled">Save and log another</button>
                        <button type="submit" class="ld-btn ld-btn-primary" wire:loading.attr="disabled">
                            Save<x-ld.kbd class="ld-kbd-on-ink">Ctrl ↵</x-ld.kbd>
                        </button>
                    </div>
                </div>
            </form>
    @endif
</div>
