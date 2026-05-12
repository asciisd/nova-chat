# Screenshots

This folder holds the screenshots referenced from the package's `README.md`
and `CHANGELOG.md`. Drop captured PNGs in here using the filenames listed
below and the references will resolve automatically — no further code
changes needed.

## Capture environment

For consistency, capture all screenshots from the same Nova install:

- A Laravel app with `asciisd/nova-chat` installed and at least one topic
  registered in `config/nova-chat.php`.
- Browser at **1440 × 900** logical viewport, **2× device pixel ratio**
  (Retina). Most modern laptops are this by default.
- Light mode unless the file is suffixed `-dark`.
- Hide browser chrome (Cmd+Shift+F on macOS Chrome) so the screenshot is
  the page only — Nova's own chrome stays visible.

Crop to the Nova content area (omit the URL bar / OS chrome). PNG, no
compression artefacts. Aim for roughly 2400 × 1500 px for hero shots and
800 × 600 px for spot shots.

## Files referenced from the repo

| File | Used in | Scene |
|---|---|---|
| `thread.png` | [README.md](../../README.md) (hero), [CHANGELOG.md](../../CHANGELOG.md) (v1.0.0) | Sidebar with the active topic tab plus 2-3 conversations, the right-hand thread open with alternating bubbles (admin replies right-aligned, user messages left-aligned), composer at the bottom. |
| `actions_menu.png` | [README.md](../../README.md) (Moderation section), [CHANGELOG.md](../../CHANGELOG.md) (v1.0.0) | The same view with the per-message kebab menu open, showing **Delete message** and **Block author** items. |
| `search.png` | [README.md](../../README.md) (sidebar callout) | Sidebar search input focused with a query typed in, sidebar filtered to matching conversations only. |

## Adding a new screenshot scene

1. Capture the PNG and drop it in this folder using the convention
   `<scene>.png` (use `<scene>-dark.png` for dark-mode variants).
2. Reference it from the relevant doc with a markdown image link:

   ```markdown
   ![Description](docs/screenshots/<scene>.png)
   ```

3. Add a row to the table above so future contributors know what the file
   is supposed to depict.

## Tips for capture

- Use a topic with a recognizable label (e.g. "Orders", "Tickets",
  "Signals") rather than the default `chat_messages` so the sidebar
  feels real.
- Seed at least three conversations so the unread badge is visible on at
  least one row.
- For the open thread, mix admin and user messages so the right-aligned
  vs left-aligned alignment is obvious.
- Don't include real customer data. Use the included factories or
  hand-crafted seed data with placeholder names.
