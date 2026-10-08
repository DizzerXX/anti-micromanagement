@auth
    @livewire(\App\Livewire\LogUpdate::class)

    {{-- L opens Log update anywhere, except while typing (HANDOFF §1). --}}
    <script>
        document.addEventListener('keydown', (event) => {
            if (event.key !== 'l' && event.key !== 'L') return
            if (event.ctrlKey || event.metaKey || event.altKey) return

            const target = event.target
            const typing = target.closest?.('input, textarea, select, [contenteditable=""], [contenteditable="true"]')
            const modalOpen = [...document.querySelectorAll('.ld-slideover, .fi-modal-window')].some((el) => el.offsetParent !== null)
            if (typing || modalOpen) return

            event.preventDefault()
            window.Livewire?.dispatch('open-log-update')
        })
    </script>
@endauth
