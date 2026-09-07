# Mierlo voor Mierlo — public source

[![hub-contracts](https://github.com/vanasten91-rgb/mierlovoormierlo-public/actions/workflows/hub-contracts.yml/badge.svg)](https://github.com/vanasten91-rgb/mierlovoormierlo-public/actions/workflows/hub-contracts.yml)

This repository contains the public WordPress source modules and automated contract tests for the Mierlo voor Mierlo platform.

## Repository layout

- `plugins/` — WordPress plugins maintained by the project.
- `themes/` — project-specific WordPress theme code.
- `tests/` — syntax, contract and smoke tests that do not require private production data.
- `.github/workflows/hub-contracts.yml` — public CI on GitHub-hosted runners.

## Clean publication boundary

This is a clean public snapshot with a new Git history. It does not contain the history of the private development repository, deployment or handoff records, production evidence, database exports, WordPress configuration, environment files, credentials, runtime storage or packaged release archives.

Do not commit secrets or personal data. Configure credentials outside the repository through the deployment environment or WordPress configuration.

## Checks

The `hub-contracts` job runs on every pull request and every push to `main`. It checks PHP syntax, validates the JavaScript contract files and executes the portable Hub smoke suite.

The workflow uses GitHub-hosted infrastructure and has read-only repository permissions. It does not connect to production.

## Development requirements

- PHP 8.4
- Node.js 22

Run an individual smoke test with PHP, for example:

```sh
php tests/mvm-hub-bootstrap-smoke.php
```

Validate an individual JavaScript contract with Node.js, for example:

```sh
node tests/mvm-hub-newsroom-backend-boundary-contract.cjs
```

## Contributions and license

Changes to `main` must be made through a pull request and pass `hub-contracts`.

No open-source license is granted by this repository. Unless a license is added later by the project owner, all rights are reserved.
