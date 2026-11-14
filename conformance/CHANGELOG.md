# Changelog

All notable changes to this package will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this
package adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

Nothing since 0.1.0.

This kit checks a driver against a specification that is itself unreleased, so its test cases
move with it: a rule added, reworded or dropped in `universal-carrier/core` changes what is
asserted here, without notice, until `1.0.0`.

From the first stable release, a new assertion on an existing rule is a minor release — it may
turn a driver that passed into one that fails, which is the point of a conformance kit — and
the test case of a new capability arrives with the minor release of the contract that adds it.

## [0.1.0] - 2026-11-14

First public pre-release.

[Unreleased]: https://github.com/Universal-Carrier/conformance/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/Universal-Carrier/conformance/releases/tag/v0.1.0
