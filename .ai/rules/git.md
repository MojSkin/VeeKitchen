# Git Rules — VeeKitchen

Standing rules for all agents and developers (set by the product owner; load-bearing):

## Branches
- **NEVER push or commit directly to `main`.** `main` receives merges from `testing` only.
- Required branches: `main` (stable/release), `production` (deployed), `testing` (integration), `development` (active work).
- **All feature branches are created FROM `development`** and named descriptively (e.g. `feature/phase1-order-core`).
- Every task = its own feature branch. After completion, merge the feature branch back into `development`.
- After merging `development` into `testing` and all tests pass, merge `testing` into BOTH `main` and `production`, then tag the release on `main` per the changelog.

## Versioning
- Versions come from `CHANGELOG.md` (SemVer: MAJOR.MINOR.PATCH).
- If there is any doubt which version applies — **ask the product owner, never guess**.

## Commits
- Commit messages in English, concise, imperative, well-formed (subject + body when needed).
- Each logical change gets its own commit on its own feature branch.

## Documentation duties (per task)
- Record completed work in `CHECKLIST.md` so nothing is done twice.
- Record every user-facing/structural change in `CHANGELOG.md`.
