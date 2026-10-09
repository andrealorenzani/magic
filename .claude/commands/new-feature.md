---
description: Run the Magic agentic workflow to design, build, review and document a feature
argument-hint: <feature idea>
---
Implement this feature for Magic: $ARGUMENTS

Run this pipeline using the project subagents, passing outputs between steps. Do not skip steps.

1. **architect** — read `docs/` and write an ADR in `docs/decisions/`. Show me a short summary; if the design breaks an existing decision in `docs/architecture.md`, STOP and ask me.
2. **implementer** — implement the ADR with tests; `php tests/run.php` must pass.
3. **reviewer** — review the result against the ADR. If verdict is CHANGES REQUESTED, send BLOCKING findings back to **implementer**, then re-review (max 3 rounds).
4. **documenter** — update `docs/architecture.md`, `docs/code.md`, `docs/roadmap.md`, `README.md`.
5. Commit the change, then **deployer** — upload it to the server (`scripts/deploy.sh`). Never write host names, domains or hosting-provider names into the repo; deployment settings live only in the gitignored `.deploy.local`.
6. Finish with a summary: what changed, test status, docs updated, deployment result, follow-ups.
