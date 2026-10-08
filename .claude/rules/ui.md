---
paths:
  - "app/Filament/**"
  - "app/Livewire/**"
  - "resources/views/**"
  - "resources/css/**"
---

# UI rules

Screen-by-screen detail is in `docs/design/HANDOFF.md`; values are in
`docs/design/tokens.css`; pictures are in `docs/design/mockups/`.

- Use Filament's own components first (tables, forms, actions, slide-overs,
  notifications, stats widgets). Write custom Blade only for the Today page and
  the report preview, and still reuse Filament components inside them.
- Reference tokens (CSS custom properties or the registered Filament colours),
  never raw hex values in Blade or PHP.
- Status always renders as icon + label + colour. Never colour alone.
- Only status, overdue and reopened use colour. Buttons, navigation and cards
  are ink and neutrals.
- Counts, dates, times and deltas use the mono font (`font-mono` / Plex Mono).
- One filled primary button per screen. Everything else is outlined or plain.
- Every form field has a visible label. Helper text sits between label and
  field. Errors appear under the field and say how to fix it.
- Icon-only buttons need an `aria-label`. Keyboard: `L` opens Log update from
  any page; Ctrl+Enter saves the open form; Esc closes slide-overs.
- Every list and table has an empty state with a next action (see HANDOFF §7).
- Copy: sentence case, plain words, no exclamation marks, no "Oops".
