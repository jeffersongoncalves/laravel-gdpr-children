# Changelog

All notable changes to this project will be documented in this file.

## 1.0.0 - 2026-11-17

First release: GDPR children's consent (Art. 8) for Laravel, part of the laravel-gdpr suite.

- Age check results only: age, method and provider. The document, selfie or birth date is never stored. Validity is set per method.
- One age per user: reliable checks outweigh self-declaration, and the youngest reliable result wins.
- The age of digital consent per member state (13 to 16; 16 by default) in the config. `needsParentalConsent()` and `canConsent()` tell you whether a consent given by the user counts.
- Parental authorisation through verified guardian links (Art. 8(2)).
- `allowsProfiling()` and `@gdprProfiling`: no profiling-based ads unless the user is a verified adult (DSA Art. 28(2)).
- The `gdpr.adult` middleware and `gdpr:children [--json]`.

## [Unreleased]
