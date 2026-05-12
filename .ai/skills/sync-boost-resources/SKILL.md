---
name: sync-boost-resources
description: Keep resources/boost/guidelines/nova-chat.md and resources/boost/skills/nova-chat-development/SKILL.md in lockstep with the actual package after any change to contracts, routes, JSON resources, config keys, or the migration stub. Use when the user mentions Boost, the consumer-facing docs, the SKILL file, or "did we update the docs?".
---

# Sync `resources/boost/` with the package

## When to use this skill

Invoke any time a code change in this repo affects what a **consumer**
of the package needs to know:

- A contract method is added, renamed, or removed.
- A controller endpoint is added or its response shape changes.
- A `config/nova-chat.php` key is added or removed.
- The migration stub gains or loses a column.
- A common consumer-side bug is identified and fixed (the troubleshooting
  matrix should grow an entry).

Also invoke when the user explicitly says:

- "Update the Boost docs."
- "Make sure the SKILL still matches."
- "Refresh the consumer-facing guidelines."

## What lives where

```
resources/boost/
├── guidelines/
│   └── nova-chat.md                         ← always-loaded conventions in consumer apps
└── skills/
    └── nova-chat-development/
        └── SKILL.md                         ← on-demand integration playbook
```

These two files are what `php artisan boost:install` (or
`boost:update --discover`) publishes into a consuming app's
`.boost/` directory.

Audience reminder:

- `resources/boost/` → developers writing **consumer apps** (talking to
  the package from outside).
- `.ai/` → developers working on **this repo** (changing the package
  from inside).

When the package's behavior changes, both sets of docs may need to
move. This skill is about keeping them honest.

## The four-section checklist

Walk this top to bottom. Each section names what to check, what to
update, and the canonical source of truth in the codebase.

### Section 1 — Contracts and traits

Source: `src/Contracts/*.php`, `src/Concerns/*.php`.

`resources/boost/guidelines/nova-chat.md` lists the three contracts and
the traits that satisfy them. If you added/removed a method or changed
its signature:

- Update the bullet list under **"Core idea — contracts, not concrete models"**.
- Update **"Non-negotiables when adding chat to a model"** if the change
  imposes a new requirement (e.g. a new required column).

`resources/boost/skills/nova-chat-development/SKILL.md` has full code
samples in **Steps 2–4**. Walk every code block and confirm it still
matches the contract and trait surface.

### Section 2 — Wire format / API surface

Source: `routes/api.php`, `src/Http/Controllers/ConversationsController.php`,
`src/Http/Resources/*.php`.

The SKILL has an **"API contract (for AI-generated client code)"**
section. Every route and every JSON field shown there must match the
real code.

Quick comparison commands:

```bash
# Routes the package mounts
rg '^Route::' routes/api.php

# Resource shapes
rg -A 30 'public function toArray' src/Http/Resources/
```

Reconcile any drift in the SKILL section, not the code (unless the code
is wrong). If a field was removed, also call it out in the
**Troubleshooting cheatsheet** so consumers searching for a missing
field find an explanation.

### Section 3 — Database stub

Source: `database/stubs/chat_messages_table.stub`.

The SKILL's **Step 1 — Create the message table migration** shows a
representative migration. It should match the stub column-for-column
(host-specific names aside). Diff them:

```bash
diff <(grep -E '\$table->' database/stubs/chat_messages_table.stub | sort) \
     <(grep -E '\$table->' resources/boost/skills/nova-chat-development/SKILL.md | sort)
```

If the stub gained a column, the SKILL gains the same column **and**
the guideline's **"Required message columns"** table gains a row.

### Section 4 — Config keys

Source: `config/nova-chat.php`.

The SKILL's **Step 5** shows a sample `config/nova-chat.php`. Every
top-level key the real config defines must appear in the sample (or be
intentionally omitted with a note saying so).

Check:

```bash
diff <(php -r 'foreach (array_keys(require "config/nova-chat.php") as $k) echo "$k\n";') \
     <(grep -oE "^\s+'[a-z_]+' =>" resources/boost/skills/nova-chat-development/SKILL.md | sort -u)
```

If the config gained a key, mention it in the SKILL with a one-line
explanation of what consumers should set it to.

## Pitfalls

- **Updating `.ai/` and `resources/boost/` independently.** They should
  drift apart deliberately, not accidentally. If a guideline applies to
  both audiences, write it once and reference it from the other.
- **Copy-pasting the SKILL's six-step playbook into the README.** The
  README is for "what is this package?" The SKILL is for "I'm a
  consumer integrating it now." Don't merge them.
- **Omitting the `morph_map` reminder when adding a new model concept.**
  The most common consumer bug is `author_type` storing FQCNs because
  someone forgot to register the morph alias. Every change that adds a
  new participant or host class to the docs should reinforce the
  morph-map requirement.
- **Treating Boost docs as auto-generated.** They aren't. Keeping them
  current is part of the same PR that changed the code.

## Anti-patterns to refuse

- "Strip the Boost guidelines down to a one-liner — consumers can read
  the README." → No. Boost guidelines are the only thing that gets
  loaded automatically into a consuming app's AI context. Brevity is
  fine; deletion is not.
- "Replace the SKILL playbook with a link to the README." → No. The
  SKILL is consumed by AI assistants in consuming apps; it has to be
  self-contained.
- "Add a 'See also' section pointing into `.ai/`." → No. `.ai/` is a
  maintainer-only folder; it's not published to consumers.
