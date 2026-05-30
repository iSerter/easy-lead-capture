# Contributing to Easy Lead Capture

Thank you for your interest in contributing!

## Conventional Commits

This project uses [Conventional Commits](https://www.conventionalcommits.org/) to automate releases. Please use the following prefixes in your commit messages:

- `feat:` for new features (bumps minor version)
- `fix:` for bug fixes (bumps patch version)
- `feat!:` or `fix!:` for breaking changes (bumps major version)
- `chore:`, `docs:`, `test:`, `refactor:`, `style:` for changes that do not affect the version

## Release Process

We use [Release Please](https://github.com/googleapis/release-please-action) to manage our releases.

1.  Merge your PR to `main`.
2.  Release Please will update a "Release PR" with the new version and changelog.
3.  When a maintainer merges the Release PR, a new tag and GitHub Release will be created automatically.

---

## Maintainer: Repository Setup Checklist

For Release Please to work correctly, ensures the following settings are enabled in this repository:

1.  **Settings → Actions → General → Workflow permissions**
    - Select **Read and write permissions**.
2.  **Settings → Actions → General**
    - Enable **Allow GitHub Actions to create and approve pull requests**.
3.  **Secrets (Optional)**
    - If you add more CI workflows (like PHPUnit) that must run on the Release PRs, create a Fine-grained Personal Access Token (PAT) with `contents: write` and `pull_requests: write` permissions, and add it as a repository secret named `RELEASE_PLEASE_TOKEN`.
