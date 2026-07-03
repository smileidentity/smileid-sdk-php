# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- Renamed the Composer package from `smile-identity/core` to `smileid/smileid`.
  The `SmileIdentity\` namespace is unchanged.
- Set the SDK version to 12.0.0, aligning the server SDKs with the V12 mobile
  SDKs.

### Added

- Initial implementation of the Smile ID V3 server-side SDK: all 14 public
  operations, internal JWT auth with caching and refresh-on-401, always-on
  telemetry headers, optional HMAC request signing (off by default), typed
  error hierarchy, idempotent-only retries, and a `waitUntilComplete` polling
  helper.
