# Task 17 — Release Please GitHub Action

## Goal

Automate semver releases for `iserter/easy-lead-capture` using [googleapis/release-please-action](https://github.com/googleapis/release-please-action). The workflow should open and maintain **Release PRs** from [Conventional Commits](https://www.conventionalcommits.org/) on `main`, bump `composer.json`, update `CHANGELOG.md`, and create GitHub releases when those PRs are merged.

## Background

This repository is a **Composer PHP library** (`composer.json` at the repo root). There is no `package.json` version to bump and no existing GitHub Actions workflows.

Release Please’s **`php`** release type is the correct strategy: it expects `composer.json` + `CHANGELOG.md` and updates the `version` field in `composer.json` on each release.

Reference: [release-please-action README](https://github.com/googleapis/release-please-action)

## How It Works (Operator View)

1. Contributors merge commits to `main` using Conventional Commits (`feat:`, `fix:`, `feat!:`, etc.).
2. On each push to `main`, the workflow runs `release-please-action`.
3. Release Please opens or updates a **Release PR** titled like `chore(main): release 1.2.0` with:
   - `CHANGELOG.md` updates
   - `composer.json` version bump
4. When ready to ship, a maintainer **merges the Release PR** (do not squash in a way that loses the release commit message unless your team has a documented alternative).
5. Release Please tags the release (e.g. `v1.2.0`) and creates a **GitHub Release** with notes from the changelog.

## Files to Create

```
.github/workflows/release-please.yml
CHANGELOG.md                          # bootstrap (can be minimal; Release PRs will expand it)
release-please-config.json            # manifest config (recommended)
.release-please-manifest.json          # current version pin for bootstrap
```

## Files to Modify

```
composer.json                         # add "version" field (bootstrap, e.g. "0.0.0" or "1.0.0")
README.md                             # short "Releasing" / "Commit messages" section
CONTRIBUTING.md                       # optional: conventional commit guidelines (create if missing)
```

## Repository Settings (Manual, One-Time)

Document these for the maintainer implementing the task:

1. **Settings → Actions → General → Workflow permissions**
   - Enable **Read and write permissions** for workflows (or ensure `contents: write` in the workflow is sufficient for your org policy).
2. **Settings → Actions → General**
   - Enable **Allow GitHub Actions to create and approve pull requests** (required for Release PRs).
3. **Secrets** (optional but recommended — see Step 2)
   - Add `RELEASE_PLEASE_TOKEN` if CI must run on Release Please PRs (see below).

## Steps

### 1. Bootstrap version files

**`composer.json`** — add a `version` field at the top level (PHP strategy reads/writes this):

```json
{
    "name": "iserter/easy-lead-capture",
    "version": "0.0.0",
    ...
}
```

Use `0.0.0` if there has never been a public release; use `1.0.0` if you are declaring the first stable release. Align with whatever is already on Packagist/tags, if any.

**`CHANGELOG.md`** — create a minimal file so the `php` strategy is satisfied:

```markdown
# Changelog

## [0.0.0](https://github.com/iserter/easy-lead-capture/compare/v0.0.0...v0.0.0) (YYYY-MM-DD)

### Features

- Initial release.
```

Release Please will rewrite/extend this on the first Release PR.

**`.release-please-manifest.json`** — pin the current version for the root package:

```json
{
  ".": "0.0.0"
}
```

Match the `composer.json` version exactly.

**`release-please-config.json`** — manifest configuration (preferred over inline `release-type` only, for future options):

```json
{
  "$schema": "https://raw.githubusercontent.com/googleapis/release-please/main/schemas/config.json",
  "packages": {
    ".": {
      "release-type": "php",
      "package-name": "iserter/easy-lead-capture",
      "changelog-path": "CHANGELOG.md"
    }
  },
  "pull-request-title-pattern": "chore${scope}: release${component} ${version}",
  "include-v-in-tag": true
}
```

Optional root-level flags to consider:

- `"bump-minor-pre-major": true` — while version &lt; 1.0.0, breaking changes bump minor instead of major (common for pre-1.0 libraries).
- `"release-as": "1.0.0"` — use **once** to force the first release version, then remove after the first Release PR merges.

### 2. GitHub Actions workflow

Create **`.github/workflows/release-please.yml`**:

```yaml
name: release-please

on:
  push:
    branches:
      - main

permissions:
  contents: write
  issues: write
  pull-requests: write

jobs:
  release-please:
    runs-on: ubuntu-latest
    steps:
      - uses: googleapis/release-please-action@v4
        id: release
        with:
          # Use a PAT if other workflows (e.g. PHPUnit) must run on Release PRs.
          # Otherwise omit and rely on the default GITHUB_TOKEN.
          token: ${{ secrets.RELEASE_PLEASE_TOKEN || secrets.GITHUB_TOKEN }}
          config-file: release-please-config.json
          manifest-file: .release-please-manifest.json
```

**Token choice:**

| Token | When to use |
|---|---|
| `GITHUB_TOKEN` (default) | Minimal setup; Release PRs and releases work; **other workflows do not run** on Release Please–created PRs/tags ([GitHub docs](https://docs.github.com/en/actions/security-for-github-actions/security-guides/automatic-token-authentication#using-the-github_token-in-a-workflow)). |
| `RELEASE_PLEASE_TOKEN` (PAT) | Use when you add CI (PHPUnit, etc.) and need checks on Release PRs. Create a fine-scoped PAT (or GitHub App) with `contents`, `pull_requests`, and `metadata` access; store as repo secret `RELEASE_PLEASE_TOKEN`. |

For v1 of this task, **`GITHUB_TOKEN` alone is acceptable** if no other workflows exist yet. Document upgrading to a PAT when CI is added (Task follow-up or same PR if CI lands together).

**Pin the action** to `@v4` (major tag). Do not use floating `@main`.

### 3. Conventional Commits policy

Add a short section to **`README.md`** (or **`CONTRIBUTING.md`**):

| Prefix | SemVer impact | Example |
|---|---|---|
| `fix:` | patch | `fix: reject invalid utm params on submit` |
| `feat:` | minor | `feat: capture utm params from embed URL` |
| `feat!:` / `fix!:` / footer `BREAKING CHANGE:` | major | `feat!: remove legacy admin password format` |
| `chore:`, `docs:`, `test:` | no release bump (typically) | `docs: document embed params` |

Squash-merge PR titles should follow the same prefixes so Release Please parses history correctly.

### 4. First release dry run (maintainer checklist)

After merging the workflow:

1. Push a `feat:` or `fix:` commit to `main` (or merge a normal PR).
2. Confirm a **Release PR** appears within a few minutes.
3. Review the PR diff: `composer.json` version, `CHANGELOG.md`, `.release-please-manifest.json`.
4. Merge the Release PR.
5. Confirm a GitHub **Release** and tag `vX.Y.Z` exist.
6. Verify `composer.json` on `main` shows the new version.

If no Release PR appears, check Actions logs and repository “Allow Actions to create PRs” setting.

### 5. Optional follow-ups (out of scope for minimal task)

Record these as future tasks, not required for acceptance:

- **PHPUnit CI workflow** triggered on `pull_request` and `push` to `main`, using `RELEASE_PLEASE_TOKEN` so CI runs on Release PRs.
- **Packagist auto-update** webhook when a GitHub release is published (Packagist.org → package → set up GitHub service hook).
- **Major/minor floating tags** (`v1`, `v1.2`) via a second job using `steps.release.outputs` (`release_created`, `major`, `minor`) — useful for GitHub Actions consumers, less common for Composer packages.

## Workflow Permissions Summary

Required in the workflow file (already shown above):

```yaml
permissions:
  contents: write    # tags, releases, changelog commits on Release PR branch
  issues: write      # release-please labels/issues (if used)
  pull-requests: write
```

## Security Notes

- Prefer a **fine-grained PAT** or GitHub App over a classic broad PAT when using `RELEASE_PLEASE_TOKEN`.
- Never commit tokens; only store in GitHub Actions secrets.
- Pin third-party actions to major versions (`@v4`), not `@main`.
- Release Please only needs write access to this repository.

## Acceptance Criteria

- `.github/workflows/release-please.yml` exists and runs on push to `main`.
- `release-please-config.json` and `.release-please-manifest.json` configure the root package with `release-type: php`.
- `composer.json` includes a `version` field; `CHANGELOG.md` exists at repo root.
- After conventional commits on `main`, a **Release PR** is opened or updated automatically.
- Merging the Release PR creates a **GitHub Release** and git tag (`v*`) with changelog body.
- `composer.json` version on `main` matches the released tag after merge.
- README documents Conventional Commit expectations for contributors.
- Repository settings checklist (workflow permissions, allow Actions to open PRs) is documented in the PR description or CONTRIBUTING.md for the implementing developer.

## Out of Scope

- Packagist publication automation (manual or separate task).
- npm / Node versioning (`package.json` is dev-only for Tailwind builds).
- Monorepo / multi-package manifests (single root `composer.json` only).
- Backfilling changelog history from pre–Conventional Commit git history (bootstrap version only).

## References

- [googleapis/release-please-action](https://github.com/googleapis/release-please-action)
- [googleapis/release-please](https://github.com/googleapis/release-please) — manifest config, supported release types
- [Conventional Commits](https://www.conventionalcommits.org/)
- PHP release type: repository with `composer.json` + `CHANGELOG.md`; bumps `composer.json` `version`
