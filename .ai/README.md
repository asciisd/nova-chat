# `.ai/` — AI guidance for working **on** `asciisd/nova-chat`

This folder is the single source of truth for AI assistants (Claude, Cursor,
and anything else that follows the same conventions) when editing this
package's own source.

It mirrors the layout of `resources/boost/` — the bundle that ships to
**consumers** of this package via Laravel Boost — but the audience is
inverted:

| Folder | Audience | Purpose |
|---|---|---|
| `resources/boost/` | Apps that `composer require asciisd/nova-chat` | "How do I integrate the package into my app?" |
| `.ai/` | Maintainers of this repo | "How do I change the package itself without breaking its contracts?" |

## Layout

```
.ai/
├── README.md                       ← you are here
├── guidelines/                     ← always-on conventions, loaded as background context
│   ├── package-architecture.md
│   ├── contract-purity.md
│   ├── frontend-workflow.md
│   ├── api-design.md
│   └── database-schema.md
└── skills/                         ← on-demand playbooks, invoked by name when the task matches
    ├── add-package-feature/SKILL.md
    ├── update-vue-ui/SKILL.md
    ├── release-new-version/SKILL.md
    └── sync-boost-resources/SKILL.md
```

## How the wiring works

- **Claude Code / Claude Desktop** — reads `CLAUDE.md` at the repo root, which
  delegates here. Skills are surfaced via `.claude/skills/*/SKILL.md` symlinks
  pointing into `.ai/skills/`.
- **Cursor** — reads `.cursor/rules/*.mdc`. Each rule is a thin wrapper that
  references one file under `.ai/guidelines/` (or the whole skill folder).
- **Anything else** — point it at `.ai/`. The directory layout is the contract.

If you change a guideline or skill, edit it **here** — the wrappers in
`CLAUDE.md` / `.cursor/rules/` should rarely need to change.

## Skill anatomy

Every skill follows the same shape (matching `resources/boost/skills/`):

```
.ai/skills/<kebab-name>/
└── SKILL.md
    ├── frontmatter (name + description)
    ├── ## When to use this skill
    ├── ## Architecture at a glance (if applicable)
    ├── ## Step-by-step playbook
    ├── ## Troubleshooting / pitfalls
    └── ## Anti-patterns to refuse
```

Keep `description` in the frontmatter to one or two sentences — it's the only
thing the model sees when deciding whether to load the rest of the skill.

## When to add a new file here

- A **guideline** when there is a recurring rule any contributor needs to
  know before touching the code (e.g. "never reference domain models from
  `src/`"). Guidelines stay loaded; keep them short.
- A **skill** when there is a multi-step procedure that only matters when the
  user is doing exactly that thing (e.g. cutting a release, refactoring the
  Vue UI). Skills can be long; they're only loaded on demand.

When in doubt: if a maintainer would re-read it every PR, it's a guideline.
If they'd re-read it once a quarter, it's a skill.
