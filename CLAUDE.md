# CLAUDE.md

You are working **inside** `asciisd/nova-chat` — a Laravel Nova package, not
a host application. Before editing any file, load the conventions and
playbooks from `.ai/`. They are the single source of truth.

## Always-on guidelines (read these first)

Read every file under `.ai/guidelines/` at session start. They're short
and they apply to almost every change in this repo:

- `.ai/guidelines/package-architecture.md` — overall layout, request
  flow, and the line between `NovaChat.php` and the service provider.
- `.ai/guidelines/contract-purity.md` — **the** rule of this repo: no
  app-namespaced classes inside `src/`. Read this before any PHP edit.
- `.ai/guidelines/frontend-workflow.md` — Vue conventions, the
  committed-`dist/` rule, polling design.
- `.ai/guidelines/api-design.md` — the five-route public API and its
  envelope.
- `.ai/guidelines/database-schema.md` — required columns, indexes,
  and why `is_from_admin` is denormalized.

## On-demand skills (load when the task matches)

Read the matching `SKILL.md` in full before doing the work:

| User intent | Skill |
|---|---|
| Add a new feature to the package itself | `.ai/skills/add-package-feature/SKILL.md` |
| Change anything in `resources/js/` | `.ai/skills/update-vue-ui/SKILL.md` |
| Cut a tagged release on Packagist | `.ai/skills/release-new-version/SKILL.md` |
| Keep `resources/boost/` consistent with the code | `.ai/skills/sync-boost-resources/SKILL.md` |

If the user's task doesn't match a skill, fall back to the guidelines
plus `.ai/README.md`.

## Two folders, two audiences — don't confuse them

- `.ai/` (this folder's pointer target) is for **maintainers** of this
  repo. Read it when editing the package's own code.
- `resources/boost/` is for **consumers** of the package — it's what
  ships via Laravel Boost into apps that `composer require asciisd/nova-chat`.
  When you change the package, `.ai/skills/sync-boost-resources/SKILL.md`
  walks you through keeping `resources/boost/` honest.

If you're ever unsure which audience a doc is written for, check the
top of `.ai/README.md` — the table there is the canonical answer.

## Rules of thumb

- Never reference `App\Models\…` from `src/`, `routes/`, `config/`, or
  `database/stubs/`. The package is generic on purpose.
- Never edit `dist/js/tool.js` or `dist/js/tool.css` by hand. They're
  generated; edit `resources/js/` and run `npm run build`.
- Never compute `is_from_admin` at query time. It's a stored column
  for a reason.
- Never break the wire format documented in `.ai/guidelines/api-design.md`
  without bumping the major version.
- When you change behavior that consumers see, update **all four** of:
  `README.md`, `resources/boost/guidelines/nova-chat.md`,
  `resources/boost/skills/nova-chat-development/SKILL.md`, and
  `database/stubs/chat_messages_table.stub` (if columns moved).
