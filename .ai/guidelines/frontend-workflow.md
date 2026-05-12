# Frontend workflow (always-on)

The Vue UI lives in this package and is **shipped pre-built**. Consuming
apps don't run the package's Vite pipeline — they load `dist/js/tool.js`
and `dist/js/tool.css` from disk.

## Where things live

```
resources/js/
├── tool.js                  Vite entry — registers the page with Nova
├── pages/Tool.vue           outer shell mounted at /nova/nova-chat
├── components/
│   ├── TopicTabs.vue        tab switcher (only renders when topics > 1)
│   ├── ConversationList.vue left sidebar — polls every 4 s
│   ├── ConversationPane.vue thread + composer — polls every 3 s, delta-only
│   ├── MessageBubble.vue    individual message
│   └── MessageComposer.vue  textarea + send button
└── lib/time.js              relative-time helper
```

`vite.config.js` builds `resources/js/tool.js` into `dist/js/tool.js` and
extracts CSS into `dist/js/tool.css` — those exact filenames are
hard-coded in `src/NovaChat.php`.

## The hard rule about `dist/`

`dist/` is **committed to the repo on purpose** (`.gitignore` has a
comment confirming this). When you change anything in `resources/js/`,
the rebuild is part of the commit:

```bash
npm install        # first time only
npm run build      # writes dist/js/tool.js + tool.css
git add dist/
```

If you forget, `composer require asciisd/nova-chat` from a tagged
release will pull stale assets and consumers will see the old UI.

For active development use the watcher:

```bash
npm run dev        # vite build --watch — keeps dist/ live
```

…and refresh the consuming app's browser.

## Talking to the API from Vue

Use `Nova.request()` — it's the axios instance Nova has already wired
with the CSRF token and base URL:

```js
const { data } = await Nova.request().get('/nova-vendor/nova-chat/topics')
```

Do **not** import axios directly. Do **not** hard-code `/nova-vendor/`
in templates — keep it in a `const BASE = '/nova-vendor/nova-chat'` at
the top of the file.

## Polling design (don't break this without thinking)

- `ConversationList` polls `GET /topics/{topic}/conversations` every
  `config.sidebar` ms (default 4000).
- `ConversationPane` polls `GET /topics/{topic}/conversations/{id}/messages?after=<lastId>`
  every `config.thread` ms (default 3000). The `after` param is what
  makes this delta-only — never drop it.
- Both pollers must pause when `document.visibilityState === 'hidden'`
  and resume on `visibilitychange`. This is the only thing standing
  between the package and a thundering-herd problem on apps with many
  open admin tabs.
- Intervals come from the API's `config` envelope (`/topics`), not from
  hard-coded numbers. If you need a new interval, add it to
  `config/nova-chat.php → poll_interval_ms` and surface it through the
  same envelope.

## Vue conventions

- Composition API + `<script setup>` only. No Options API in new code.
- Tailwind utility classes only — Nova ships its own Tailwind build,
  so the same classes work without a separate config.
- Heroicons via inline SVG copy-paste (no icon library dependency).
- No new runtime dependencies without a discussion. The dist bundle
  ships verbatim — every kB lands in every consuming app.
