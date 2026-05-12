---
name: release-new-version
description: Cut a new tagged release of asciisd/nova-chat for Packagist — covers the version bump, dist rebuild, sanity checks against the contracts, README/CHANGELOG updates, and the actual git tag. Use when the user says "release", "tag", "publish version", or asks to ship recent work.
---

# Release a new version

## When to use this skill

Invoke when the user wants to publish a new tagged release. Typical phrasings:

- "Release v1.2.0."
- "Tag a new version."
- "Cut a patch release for the unread-badge fix."
- "Ship what's on `main`."

Do **not** invoke for:

- Pre-release prep that's already underway in a feature branch (the user
  hasn't asked to release yet).
- Internal-only branches that won't be tagged.

## SemVer rules for this package

The package's public surface is:

1. The three contracts in `src/Contracts/` (interface methods).
2. The wire format documented in `.ai/guidelines/api-design.md`.
3. The `config/nova-chat.php` keys.
4. The route paths under `/nova-vendor/nova-chat/`.
5. The publishable migration stub.

Bumping rules:

| Change | Bump |
|---|---|
| New optional method on a contract **with a non-DB-dependent trait default** | minor |
| New abstract method on a contract, OR new required column in the stub | **major** |
| New JSON field in a response, no removals | minor |
| Removed/renamed JSON field, or changed type | **major** |
| New config key with a sensible default | minor |
| Removed/renamed config key | **major** |
| Bug fix, internal refactor, Vue-only tweaks | patch |

When in doubt, choose the higher bump. Nova plugins live inside
production admin panels — surprise breakage is expensive.

## The eight-step playbook

### Step 1 — Confirm the working tree is clean

```bash
git status
git pull --ff-only
```

Working tree must be clean before anything else. Don't release with
uncommitted changes — `dist/` rebuilds will silently include them.

### Step 2 — Choose the version number

```bash
git tag --sort=-v:refname | head -5
```

Pick the next version per the SemVer table above. Common script:

```bash
NEW_VERSION="v1.x.y"
PREV_VERSION="$(git tag --sort=-v:refname | head -1)"
```

### Step 3 — Skim the diff for breaking changes

```bash
git log --oneline "$PREV_VERSION"..HEAD
git diff "$PREV_VERSION"..HEAD -- src/Contracts/ src/Http/Resources/ config/ database/stubs/ routes/api.php
```

If anything in those paths changed in a non-additive way, the bump in
Step 2 must be **major**. Re-pick if necessary.

### Step 4 — Rebuild and commit `dist/`

The compiled bundle ships verbatim; it must match the source on the
release commit.

```bash
npm install
npm run build
git status dist/
```

If `git status dist/` shows changes, commit them:

```bash
git add dist/
git commit -m "chore(dist): rebuild for $NEW_VERSION"
```

### Step 5 — Update `README.md` and `CHANGELOG.md`

- README: only if the public surface changed.
- CHANGELOG: always. Use Keep a Changelog format if a `CHANGELOG.md`
  already exists; otherwise create one with one entry for this release
  and note that earlier history is reconstructed from git.

### Step 6 — Sanity-check the contracts haven't leaked

```bash
rg -n '\\App\\\\Models\\\\' src/ routes/ config/ database/stubs/ \
  && { echo 'CONTRACT VIOLATION — fix before tagging'; exit 1; } \
  || echo 'Contracts clean'
```

Also confirm the routes the README documents still exist:

```bash
php artisan route:list 2>/dev/null | grep nova-chat || true
```

(That command needs to run inside a host app; skip if releasing from
the package repo standalone.)

### Step 7 — Tag and push

```bash
git tag -a "$NEW_VERSION" -m "Release $NEW_VERSION"
git push origin main
git push origin "$NEW_VERSION"
```

`composer.json` does **not** carry a `version` field — Composer reads
the tag. Don't add a `version` field "for clarity"; it just creates a
second source of truth that drifts.

### Step 8 — Confirm Packagist picked it up

If Packagist is set up with a webhook (the standard flow):

```
https://packagist.org/packages/asciisd/nova-chat
```

…should show the new version within ~60 seconds. If not, log into
Packagist and click **Update**.

## Pitfalls

- **Releasing without rebuilding `dist/`.** Consumers pull stale JS even
  though the source is correct. Step 4 is non-optional.
- **Adding a `version` field to `composer.json`.** Don't. The git tag
  is the source of truth.
- **Tagging from a dirty working tree.** The tag points at the last
  commit, not your uncommitted edits — your release ships missing files.
- **Choosing a patch bump for a contract method addition.** Even if the
  trait provides a default, every consumer that has hand-rolled an
  implementation of the contract (without using the trait) breaks. When
  in doubt, minor.
- **Skipping the changelog.** Consumers reading "what changed in 1.4"
  shouldn't have to read the diff.

## Anti-patterns to refuse

- "Force-push a tag." → No. Tags are immutable once published. Cut a
  new patch instead.
- "Release straight from a feature branch." → No. Tags should point at
  `main` or a release branch with the merge commit.
- "Skip rebuilding `dist/` because the JS hasn't changed." → Verify
  with `npm run build && git status dist/`. Vite outputs are
  deterministic enough that an unchanged source produces an unchanged
  bundle, but verify, don't assume.
