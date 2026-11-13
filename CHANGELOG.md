# Changelog

## [Unreleased]

## [1.0.1] - 2026-11-13

### Changed

- Neutral example data in docs and tests.

## [1.0.0] - 2026-11-13

### Added

- `policy.json`: the service's usage policy as data (limits, licence, credit, what a request needs), read at its pages on 2026-11-10 and named in `composer.json` (`extra.omnisense.policy`); the core honours it on every request.

### Fixed

- Without a key, the works of an author and a search page past the first page: OpenAlex refuses a
  cursor past the first page without a key (401, "API key required for cursor paging", seen on
  2026-11-13), so the pages are numbered then (`page=N`, OpenAlex's basic paging, the first 10,000
  results; `Page::$next` is `page:2`...). With a key, or given one of OpenAlex's cursors, the cursor
  as before. `Collector::collect()` over OpenAlex failed on an author with more than one page.
- `paratext` is read as a boolean whatever its spelling (omnisense/omnischolar's `Config::bool()`):
  PHP's `(bool)` read the string `'false'` - a site's YAML or environment gives one - as true, and
  dropped the `is_paratext:false` filter. A value that is no boolean is an `InvalidConfigException`.
- The factory passes `throttle` (seconds between two calls, which every factory takes) to the
  source: `throttle: 2.5` gave a source that left no interval.

### Changed

- Requires `omnisense/omnischolar: ^1.0`: stable versions, so `composer require omnischolar/openalex` installs in a project at the default minimum-stability. `extra.branch-alias` names 1.x (`1.x-dev`); the CI (`.github/workflows/ci.yml`, glitchr-studio/ci's shared workflow) and the release on a tag (`release.yml`).

