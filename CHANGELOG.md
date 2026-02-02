# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Added
- Schema-compliant JSON report output according to MultiFlexi Application Report Schema
- Comprehensive test coverage for JSON report generation
- New report methods in ZalohyZeSmluvDoZavazku and ZalohyZeSmluvDoPohledavek classes
- JSON output validation tests
- Integration tests for schema compliance

### Changed  
- Updated all main scripts (GenerujFakturyZeSmluv.php, GenerujZavazkyZeSmluv.php, GenerujPohledavkyZeSmluv.php) to generate schema-compliant JSON output
- JSON reports now include producer, status, timestamp, message, artifacts, and metrics fields
- Enhanced error and warning status detection from application messages
- Improved documentation in README.md with JSON output examples

### Fixed
- Standardized JSON output format across all scripts
- Proper ISO8601 timestamp formatting
- Error handling and status reporting

## Previous versions

See git history for changes in previous versions.