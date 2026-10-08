@auth
    @livewire(\App\Livewire\LogUpdate::class)

    {{-- L opens Log update and / focuses search, except while typing (HANDOFF §1). --}}
    <script>
        document.addEventListener('keydown', (event) => {
            if (event.ctrlKey || event.metaKey || event.altKey) return
            if (! ['l', 'L', '/'].includes(event.key)) return

            const typing = event.target.closest?.('input, textarea, select, [contenteditable=""], [contenteditable="true"]')
            const modalOpen = [...document.querySelectorAll('.ld-slideover, .fi-modal-window')].some((el) => el.offsetParent !== null)
            if (typing || modalOpen) return

            event.preventDefault()

            if (event.key === '/') {
                document.querySelector('.ld-topbar .fi-global-search-field input')?.focus()
            } else {
                window.Livewire?.dispatch('open-log-update')
            }
        })
    </script>
@endauth
