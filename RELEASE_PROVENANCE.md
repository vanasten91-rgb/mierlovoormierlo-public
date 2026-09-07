# MvM public release provenance

This file records the one-time provenance binding used to transition the sanitized public repository into the protected release-authority role.

## Source binding

- Private source repository: `vanasten91-rgb/mierlovoormierlo`
- Private source commit: `380f9da760a73dedffa11f9a650851de0cb89e60`
- Original public root commit: `cfa7c64480ed181c790cb136ae1d2215750f176c`
- Public repository: `vanasten91-rgb/mierlovoormierlo-public`

## Git tree evidence

The publication was verified with Git object identities, so equal tree/blob SHA values mean byte-for-byte equal Git content.

- `plugins/`
  - private tree: `10882baf51e54750391212d8228a69f47d38a0c8`
  - public tree: `10882baf51e54750391212d8228a69f47d38a0c8`
  - result: exact match
- `themes/`
  - private tree: `278f6089230fdd84e3a88043e68a98f5e38fb0ec`
  - public tree: `278f6089230fdd84e3a88043e68a98f5e38fb0ec`
  - result: exact match
- `tests/`
  - private tree: `d84761b28877f39a82a3ac795f6ce0941e3ba9fc`
  - public sanitized tree: `8730ce0c8c458d320654e1d35e06467cb858ed48`
  - result: intentionally different tree because private/live/evidence-only tests were omitted from publication; retained public test blobs were verified as unchanged members of the private test tree.

## Publication boundary

No private Git history, deployment/handoff records, production evidence, account inventories, environment files, WordPress configuration, database exports, credentials, runtime storage, private packaged releases, or self-hosted/private-infrastructure workflows are part of the public release boundary.

## Authority rule

This provenance record by itself does not authorize a release. A release is authoritative only when all of the following hold:

1. the commit is on protected public `main`;
2. the required `hub-contracts` check is green and strict/up-to-date;
3. the workflow verifies the recorded publication tree identities;
4. the workflow builds the release artifact from the exact checked-out source commit;
5. artifact SHA-256 evidence is published by that exact workflow run.

After this transition, protected public `main` plus its exact green CI artifact becomes the release authority. The private repository remains development/history context and is not mutated by this transition.

## Subsequent release update — backend style alignment (2026-09-07)

The protected public release authority remains the source of release artifacts. This update originated from private development commit `94d032380808a804a16b302f0d1120092f370ae4`, whose exact-head MvM Hub Newsroom run #1082 completed successfully before publication.

- Scope: MvM Hub / Newsroom backend style alignment plus portable style and backend-boundary contracts.
- Private `plugins/` tree: `4d0938f25c693100daeb00ad87e440d3c6e2c88d`
- Public `plugins/` tree before this provenance/workflow commit: `4d0938f25c693100daeb00ad87e440d3c6e2c88d`
- Result: exact plugin-tree match.
- Private full `tests/` tree: `ca4248f18ed121827109e32845676cd5e28f87ef`
- Public sanitized `tests/` tree: `20bddbe3811b18c3da11aea52503b3cb3aeb9834`
- Result: intentionally different because private/live/evidence-only tests remain outside the public boundary; the retained style and backend-boundary contract files are published unchanged.
- `themes/` remains `278f6089230fdd84e3a88043e68a98f5e38fb0ec`.

No private Git history, environment configuration, credentials, runtime storage or production evidence was published by this update. The public workflow is updated to verify these new exact tree identities and to execute the portable JavaScript contracts before building a release artifact.
