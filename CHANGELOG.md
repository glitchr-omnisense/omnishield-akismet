# Changelog

## [Unreleased]

## [1.0.0] - 2026-11-13

The first stable release. Omnishield Akismet: is this content spam - comment-check (ham, spam, or flagrant spam to discard), submit-spam and submit-ham to teach it, verify-key; a key or transport error raised, never taken for ham.

### Changed

- Requires `omnisense/contracts: ^1.0`, `omnisense/omnishield: ^1.0`: stable versions, so `composer require omnishield/akismet` installs in a project at the default minimum-stability. `extra.branch-alias` names 1.x (`1.x-dev`); the CI (`.github/workflows/ci.yml`, glitchr-studio/ci's shared workflow) and the release on a tag (`release.yml`).
- `phpstan-baseline.neon`, empty: the shared workflow requires the file; nothing is accepted.
