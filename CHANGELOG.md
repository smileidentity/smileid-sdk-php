# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Initial implementation of the Smile ID V3 server-side SDK: all 14 public
  operations, internal JWT auth with caching and refresh-on-401, always-on
  telemetry headers, optional HMAC request signing (off by default), typed
  error hierarchy, idempotent-only retries, and a `waitUntilComplete` polling
  helper.
