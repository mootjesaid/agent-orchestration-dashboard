# AI Development Orchestrator — product requirements

Status: proposed specification; implementation is not authorized. Updated 2026-11-17.

## Purpose and outcome

Turn a development idea, feature or bug report into reviewed changes in a local
repository while preserving human control. The central product is an intelligent
orchestration system; its dashboard makes decisions, evidence and controls visible.
The user should not have to coordinate agent prompts, reconstruct interrupted work
or remember which version of a plan was approved.

## Confirmed product scope

The initial user is a single developer on native Windows or macOS, managing multiple
local Git repositories. Codex uses existing ChatGPT sign-in. The preferred stack is
Laravel, React, TypeScript, Inertia, Tailwind, shadcn/ui, SQLite and Laravel Reverb.
Use the existing starter; no reinstall. No installation or functionality belongs in
this documentation phase. See [decisions](DECISIONS.md) for remaining choices.

## Actors and responsibilities

| Actor | Owns | Restrictions |
| --- | --- | --- |
| User | Requirements, plan approval, sensitive permissions, optional commit approval | Can pause/cancel; approval is explicit and auditable |
| Laravel engine | Valid transitions, task graph, session lifecycle, permissions, retries, concurrency, persistence, Git | AI suggestions never bypass its guards |
| Orchestrator AI | Understand request, inspect context, clarify, decide research, evaluate plans, assign work, route corrections, report | Never implements application code or grants its own permissions |
| Planner AI | Read-only research and structured plan | No project-file writes, implementation, setup scripts or mutating MCP tools |
| Executor AI | Implement assigned approved tasks, report evidence | No scope expansion, protected-branch work, unauthorized Git writes or delegation |
| Reviewer AI | Independent requirements/diff/test review with evidence | Cannot correct code or approve its own implementation |

## Functional requirements and acceptance criteria

Stable IDs below must appear in implementation tasks and final release verification.

| ID | Requirement | Acceptance criterion |
| --- | --- | --- |
| FR01 | Register multiple local repositories | Canonical repository identity, path and base branch are verified; invalid/outside-root paths fail; user changes are preserved |
| FR02 | Conversational request and clarification | Persist idea, replies, decisions and unresolved questions; inspect available context before asking; user can amend requirements |
| FR03 | Applicable project instructions | Capture AGENTS/overrides, CLAUDE and relevant project rules/skills with path, scope and content hash; surface conflicts; changed instructions invalidate stale context |
| FR04 | Necessary research only | Record why research was needed, official sources and date; reuse valid findings; do not launch duplicate research agents |
| FR05 | Strict read-only Planner | Enforced denial of project writes through file tools, shell and MCP; return all required plan fields; invalid output cannot advance workflow |
| FR06 | Explicit approved plan | Show requirements, acceptance criteria, diff scope, tasks, dependencies, risks, tests and resources; execution cannot begin without approval of the displayed immutable revision |
| FR07 | Controlled execution | One executor by default; tasks receive the approved context; dependency, model/effort, workspace and budget policies enforced server-side |
| FR08 | Independent review and correction | Fresh reviewer sees original requirements, approved plan, full relevant diff and test evidence; findings have severity/evidence; fixes trigger new tests/review |
| FR09 | Safe branch/worktree workflow | Every development task has a feature branch; own worktree by default; main/develop never edited; dirty user work and external repository changes cannot be silently overwritten |
| FR10 | Optional commit | OFF waits for explicit commit approval; ON still requires a valid review and tests; human-review-required forces human approval; record exact commit and evidence; never auto push/merge/deploy |
| FR11 | Permissions and audit | Plan approval, tool escalation and commit approval are distinct; sensitive/destructive requests show exact scope; stale/denied requests cannot execute |
| FR12 | Sessions and controls | Show role, task, thread/turn, model/effort, status and tool activity; support start, interrupt, reconciled resume and terminate without fabricated success |
| FR13 | Real-time dashboard and history | Durable ordered timeline; reconnect catches up from stored events; complete clarification/approval/review/history accessible without raw stream dependence |
| FR14 | Usage and limits | Record reported usage without double counting; show estimate provenance and unknown values; enforce bounded concurrency and best-effort token/time limits |
| FR15 | Recovery | Restart during planning, execution, pending permissions, tests or commit yields a reconciled safe state without duplicate mutation or invented approval |
| FR16 | Cross-platform local operation | Supported end-to-end workflow, sandbox-denial and process cleanup scenarios pass on native Windows and macOS |

## User experience

Register/select a repository, describe the request and answer only unresolved
questions. Review a proposed plan and either approve, reject or request revision.
Watch task progress, tools and permission prompts. Inspect review findings and
correction history. With auto commit OFF, inspect the final diff and explicitly
approve a local commit or leave changes uncommitted. Pausing and cancelling keep
partial changes and evidence visible.

Initial screens: repository/workflow overview; request conversation; plan and
approval; workflow detail with tasks/timeline/session activity; review/diff; settings.
Add richer dependency graphs, agent availability dashboards and comparative cost
analytics after the safe single-executor path works. No artificial agent marketplace
or complex dashboard is required to prove orchestration.

## Nonfunctional requirements

- Security: loopback binding, authenticated UI, CSRF protection, private broadcasts,
  authorized repository paths, minimal child-process environment and secret redaction.
  Repository content and model output are untrusted input to the policy engine.
- Durability: record decisions before dispatch, detect duplicate commands and stale
  leases, preserve artifacts across restarts; never promise exactly-once external I/O.
- Responsiveness: HTTP requests enqueue/record commands rather than wait for agents.
  Proposed MVP target: controls acknowledge within two seconds under normal local
  load; timeline updates visible within two seconds after persistence. Measure in T21.
- Efficiency: targeted context manifests, source reuse, one executor, bounded research,
  repair loops and output. No token-by-token SQLite writes.
- Portability: native OS process adapters; spaces/Unicode in paths; no mandatory WSL,
  Docker or Redis. Explicitly publish tested OS/runtime versions at release.
- Privacy: local workflow storage, but selected repository content is sent to Codex;
  do not describe the product as offline inference. Retention policy is explicit.
- Accessibility: keyboard-accessible approvals, readable diffs/status, responsive
  layout; never encode severity or session state through color alone.

## Release boundaries

MVP: single local user/multiple registered repositories, clarification, read-only
planning, immutable approvals, one active execution slot, independent review,
human commit approval by default, recovery and basic dashboard. Include the auto
commit setting and task override before MVP completion, default OFF.

Later: justified parallel executors with integration review, native subagent use
where bounded, richer session controls/analytics and API-key authentication.
Remote runners, multi-user hosting, automatic push/merge/deploy, autonomous scope
expansion, arbitrary plugin installation and distributed scheduling are out of MVP.

## Product completion evidence

For a small feature in a disposable repository on each OS, demonstrate the complete
workflow and persist its plan, approval, tests, independent review and commit outcome.
Also demonstrate rejection/revision, write denial, human-review override, failing
tests, review corrections, budget exhaustion, process interruption and restart. A
dashboard-only demonstration does not satisfy the product acceptance criteria.
