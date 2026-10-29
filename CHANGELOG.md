# Changelog

## [Unreleased]

### Fixed

- `paratext` is read as a boolean whatever its spelling (omnisense/omnischolar's `Config::bool()`):
  PHP's `(bool)` read the string `'false'` - a site's YAML or environment gives one - as true, and
  dropped the `is_paratext:false` filter. A value that is no boolean is an `InvalidConfigException`.
- The factory passes `throttle` (seconds between two calls, which every factory takes) to the
  source: `throttle: 2.5` gave a source that left no interval.
