# Task 19 — Fix Release Please Git Tags (`v0.1.0` not `iserter/easy-lead-capture-v0.1.0`)

## Goal

Release Please must create **simple semver git tags** (`v0.1.0`, `v1.2.3`) suitable for Packagist and Composer, not tags prefixed with the Composer package name.

## Background

Task 17 configured [release-please](https://github.com/googleapis/release-please) with manifest mode. The first release ([v0.1.0 on GitHub](https://github.com/iSerter/easy-lead-capture/releases/tag/iserter%2Feasy-lead-capture-v0.1.0)) created tag **`iserter/easy-lead-capture-v0.1.0`** instead of **`v0.1.0`**.

### Root cause

In manifest mode, Release Please defaults **`include-component-in-tag` to `true`**. When a component name is present, tags follow:

```text
<component-name>-v<release-version>
```

Our `release-please-config.json` sets:

```json
"package-name": "iserter/easy-lead-capture"
```

That value becomes the component name, so the tag becomes `iserter/easy-lead-capture-v0.1.0` (the `/` is valid in git tags but awkward in URLs and wrong for a single-package repo).

`include-v-in-tag: true` is already correct — the problem is the **component prefix**, not the `v`.

### What Packagist expects

Packagist discovers versions from **git tags** on the connected VCS repository. For a single Composer library at the repo root, tags should be standard semver refs: `v0.1.0`, `1.0.0`, etc. — not monorepo-style `{package-name}-v{version}` tags (those are for repos releasing multiple packages from one tree).

Reference: [release-please manifest docs — `include-component-in-tag`](https://github.com/googleapis/release-please/blob/main/docs/manifest-releaser.md)

## Files to Modify

```
release-please-config.json       # add include-component-in-tag: false
docs/tasks/17-release-please.md  # document the setting (prevent regression)
```

## Steps

### 1. Update `release-please-config.json`

Add at the **root** of the config (applies to all packages):

```json
"include-component-in-tag": false
```

Resulting relevant excerpt:

```json
{
  "$schema": "https://raw.githubusercontent.com/googleapis/release-please/main/schemas/config.json",
  "include-component-in-tag": false,
  "include-v-in-tag": true,
  "packages": {
    ".": {
      "release-type": "php",
      "package-name": "iserter/easy-lead-capture",
      "changelog-path": "CHANGELOG.md"
    }
  },
  "pull-request-title-pattern": "chore${scope}: release${component} ${version}"
}
```

**Keep** `package-name` — it is still used for changelog grouping and release PR titles; it should no longer affect the git tag when `include-component-in-tag` is `false`.

**Remove** `"release-as": "0.1.0"` if it is still present (bootstrap-only; should not remain after the first release).

Do **not** change `.release-please-manifest.json` or `composer.json` version for this fix alone.

### 2. Remediate the mistaken `v0.1.0` release (maintainer, one-time)

The incorrect tag/release already exists on GitHub. After merging the config fix, clean up manually (order matters for Packagist):

1. **Delete the GitHub Release** tied to `iserter/easy-lead-capture-v0.1.0` (Releases UI → delete release; choose whether to delete the tag when prompted).
2. **Delete the git tag** `iserter/easy-lead-capture-v0.1.0` locally and on `origin` if it still exists:
   ```bash
   git push origin --delete 'iserter/easy-lead-capture-v0.1.0'
   ```
3. **Create the correct tag** on the commit that contains `0.1.0` (the merge commit of the Release PR, currently `388b118` or whatever is on `main` at `composer.json` version `0.1.0`):
   ```bash
   git tag v0.1.0 <commit-sha>
   git push origin v0.1.0
   ```
4. **Recreate the GitHub Release** for tag `v0.1.0` with the same changelog body as `0.1.0` (copy from the old release or `CHANGELOG.md`).
5. **Packagist** — open [packagist.org](https://packagist.org) → package `iserter/easy-lead-capture` → **Update** (or wait for the hook). Confirm `0.1.0` appears. If the wrong tag was indexed, contact Packagist support or delete the erroneous dev version from the package page after the correct tag exists.

Optional: add a short note in the PR description that future releases will tag as `v*` automatically once this config merges.

### 3. Verify the next release

After the config change is on `main`:

1. Merge a conventional commit (`fix:` or `feat:`) if needed to trigger Release Please.
2. Merge the resulting Release PR (e.g. `0.1.1`).
3. Confirm the new git tag is **`v0.1.1`**, not `iserter/easy-lead-capture-v0.1.1`.
4. Confirm the GitHub Release name/tag matches.

### 4. Update Task 17 documentation

In `docs/tasks/17-release-please.md`, add to the sample `release-please-config.json` and the “First release dry run” checklist:

- `"include-component-in-tag": false` — required for single-package Composer repos so tags are `v*`.
- Note that `package-name` must **not** be relied on for tag naming; it is for changelog/PR metadata only when component-in-tag is disabled.

## Acceptance Criteria

- `release-please-config.json` sets `"include-component-in-tag": false` at the root.
- Git tag `v0.1.0` exists on the `0.1.0` release commit; incorrect tag `iserter/easy-lead-capture-v0.1.0` is removed from GitHub.
- GitHub Release for `0.1.0` points at tag `v0.1.0` (not the package-prefixed tag).
- Packagist lists version `0.1.0` from the `v0.1.0` tag (or documents any manual sync step taken).
- The next automated release creates tag `vX.Y.Z` only.
- Task 17 doc updated so future setups do not repeat the mistake.

## Out of Scope

- Changing Packagist package name or VCS URL.
- Monorepo / multi-package tagging (not applicable).
- Rewriting `CHANGELOG.md` history.

## References

- [Incorrect release](https://github.com/iSerter/easy-lead-capture/releases/tag/iserter%2Feasy-lead-capture-v0.1.0)
- [release-please: `include-component-in-tag`](https://github.com/googleapis/release-please/blob/main/docs/manifest-releaser.md)
- Task 17 — `docs/tasks/17-release-please.md`
