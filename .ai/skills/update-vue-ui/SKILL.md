---
name: update-vue-ui
description: Modify the Vue components shipped inside asciisd/nova-chat (TopicTabs, ConversationList, ConversationPane, MessageBubble, MessageComposer) and rebuild the dist/ bundle correctly so the change reaches consumers.
---

# Update the Vue UI

## When to use this skill

Invoke when the user asks to change anything visible inside Nova at
`/nova/nova-chat`:

- "Make the unread badge red instead of blue."
- "Add a typing indicator to the composer."
- "Show avatars next to message bubbles."
- "Change the relative-time format in the sidebar."
- "Pause polling immediately on tab blur (currently waits for the next interval)."

Do **not** invoke when:

- The change is server-side only (use `add-package-feature` instead).
- The user wants to override the UI from inside their app — the package
  doesn't expose Vue overrides; suggest a fork or a feature request.

## Architecture at a glance

```
resources/js/tool.js
   └─ Nova.booting(Vue => Vue.component('NovaChat', Tool))

resources/js/pages/Tool.vue   ← outer shell, fetches /topics on mount
   ├─ TopicTabs.vue
   ├─ ConversationList.vue    ← polls /topics/{key}/conversations every 4 s
   └─ ConversationPane.vue    ← polls /topics/{key}/conversations/{id}/messages
        ├─ MessageBubble.vue
        └─ MessageComposer.vue

vite.config.js  → dist/js/tool.js + dist/js/tool.css
src/NovaChat.php (Tool::boot) registers those exact paths with Nova.
```

`dist/` is committed on purpose (see `.gitignore` comment).

## The four-step playbook

### Step 1 — Find the right component

Quick map:

| User says… | Edit this file |
|---|---|
| "the tabs at the top" | `resources/js/components/TopicTabs.vue` |
| "the sidebar" / "conversation list" / "unread count" | `resources/js/components/ConversationList.vue` |
| "the thread" / "the right panel" | `resources/js/components/ConversationPane.vue` |
| "the message bubble" / "alignment" / "timestamps inside the thread" | `resources/js/components/MessageBubble.vue` |
| "the input box" / "send button" / "max length" | `resources/js/components/MessageComposer.vue` |
| "the empty state when no topics are configured" | `resources/js/pages/Tool.vue` |
| "relative time formatting" | `resources/js/lib/time.js` |

If the change spans more than two of these, prefer one well-named
helper in `lib/` over duplicating logic across components.

### Step 2 — Make the change

Conventions (see `.ai/guidelines/frontend-workflow.md` for the full
list):

- Composition API + `<script setup>` only.
- Tailwind utility classes only — no custom CSS files. Nova ships a
  Tailwind build, so the same utilities work without a separate config.
- HTTP via `Nova.request()` — never import axios directly.
- Heroicons via inline `<svg>` (no icon library dependency).
- No new npm runtime dependencies without a discussion.

When you need new data from the server, follow `add-package-feature`
to add it to the JSON resource first. Don't fetch new endpoints just
for the UI.

### Step 3 — Rebuild `dist/` and commit it

```bash
npm install      # first time only
npm run build    # writes dist/js/tool.js + dist/js/tool.css
git add resources/js dist/
```

For iterative work, leave `npm run dev` running in a separate terminal —
it watches `resources/js/` and rewrites `dist/` on every save. Refresh
the consuming app's browser to see the change.

If you forget to commit `dist/`, the release will ship stale JS even
though your source changes look right. **Always** run
`git status dist/` before opening the PR.

### Step 4 — Verify against a real Nova install

The package can't fully test its UI in isolation — there's no host
model. Use the consuming-app workflow:

1. In a sibling Laravel app, point composer at this directory:

   ```json
   "repositories": [
       { "type": "path", "url": "../nova-chat" }
   ],
   "require": {
       "asciisd/nova-chat": "*"
   }
   ```

2. `composer update asciisd/nova-chat`.
3. Sign into Nova, click **Chat**, and exercise the UI you changed.
4. Open DevTools → Network and watch the polling cadence stays at
   4 s (sidebar) and 3 s (thread). Anything else means the
   `config.sidebar` / `config.thread` plumbing broke.

## Pitfalls

- **Editing `dist/js/tool.js` directly.** That file is generated; your
  edit will vanish on the next `npm run build`. Always edit `resources/js/`.
- **Polling that doesn't pause on `visibilitychange`.** Causes a
  thundering herd on apps with many open admin tabs. Test by switching
  away from the tab and watching the Network panel — requests must stop.
- **Dropping the `?after=<lastId>` query param** in the thread poller.
  That turns delta-only polling into full-thread polling, multiplying
  payload size by N.
- **Hard-coding `/nova-vendor/nova-chat`** in templates. Keep it in a
  single `const BASE` per file.
- **Adding a runtime dependency.** Every kB ships in every consumer's
  Nova bundle. Inline it or push back.
- **Using the Options API.** New code is `<script setup>` only.

## Anti-patterns to refuse

- "Override these Vue components from my app." → Not supported in v1.
  The package's `dist/` is the rendered UI; consumers don't run Vite
  for it. Suggest a forked package or a feature request.
- "Switch from polling to WebSockets in this PR." → A v2 conversation,
  not a UI-only change.
- "Inline message HTML so we can render rich text from the server." →
  XSS-by-default. Render with `{{ }}` and add a sanitizer if rich text
  is genuinely needed.
