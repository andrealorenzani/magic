---
description: Run the Magic agentic workflow to design, build, review and document a feature
argument-hint: <feature idea>
---
Implement this feature for Magic: $ARGUMENTS

You are the orchestrator: do not write code or docs yourself; pass outputs between the project subagents. Do not skip steps.

0. Read `docs/architecture.md` and `docs/code.md`. If the request is ambiguous, ask the user (AskUserQuestion) before going on.
1. **architect** — writes an ADR in `docs/decisions/` and ends with `ARCHITECTURE CHANGE: yes/no`. Show me a short summary; if yes (or it breaks a decision in `docs/architecture.md`), STOP and ask me.
2. **implementer** — implements the ADR with tests; `php tests/run.php` must pass.
3. **reviewer** — reviews against the ADR; blocking findings go back to **implementer**, then re-review (max 3 rounds). Keep its "Docs drift" list.
4. **documenter** — gets a summary of the change plus the drift list; updates `docs/architecture.md`, `docs/code.md`, `docs/features.md`, `docs/roadmap.md`, `README.md` and always `docs/changelog.md`.
5. Run `php tests/run.php` yourself, commit, then **deployer** — upload it to the server (`scripts/deploy.sh`). Never write host names, domains or hosting-provider names into the repo; deployment settings live only in the gitignored `.deploy.local`.
6. Finish with a summary: what changed, test status, docs updated (incl. changelog), deployment result, follow-ups.
