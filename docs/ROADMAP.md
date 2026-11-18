# Implementation roadmap

Updated 2026-11-18. Phase 1 has Windows protocol and review evidence; the native isolation gate remains blocked.
T05 is approved with T04 deferred for that task. T06 has separate approval. T07 onward remains proposed. Measured evidence is in
[CAPABILITY-VALIDATION.md](CAPABILITY-VALIDATION.md). Detailed criteria/dependencies are
in the [task index](../tasks/README.md); pending choices in [DECISIONS.md](DECISIONS.md).

## Phase 0 — documentation (complete)

Deliver requirements, feasibility evidence, architecture, orchestration contracts,
decision register, roadmap, task backlog and durable AGENTS/.agents memory. No runtime
code, installations, executable role configurations or commits are part of this phase.

Exit: user reviews findings and selects the next approved scope. Approval of a document
alone must not be interpreted as permission for every task in this roadmap.

## Phase 1 — capability and safety gates (Windows follow-up recorded, exit blocked)

- [T01](../tasks/T01.md): pin Codex protocol/version and read-only capability evidence.
- [T02](../tasks/T02.md): Windows milestone for native permissions, PHP stdio and process proof. macOS validation is deferred for release.
- [T03](../tasks/T03.md): native independent review suitability and verdict contract.

Windows protocol evidence includes clean cross-process thread resume and active
turn interruption. Use native review for supplemental findings and an independent
structured turn for a machine verdict, with human review when evidence is incomplete.
Unattended execution remains blocked pending T02's full role, Git and native
lifecycle proofs. macOS validation remains deferred under Q02. Q03 selects
standalone executor clones and Laravel-controlled Git. The Windows clone probe
failed the workspace-only write check, so enabling executor writes remains gated.

## Phase 2 — durable local foundation

- [T04](../tasks/T04.md): existing starter/native development baseline.
- [T05](../tasks/T05.md): durable state and guarded transitions.
- [T06](../tasks/T06.md): command journal, outbox, leases and deduplication.
- [T07](../tasks/T07.md): stdio adapter and role-session lifecycle.
- [T08](../tasks/T08.md): trusted local Git broker and durable registration implemented. Executor integration remains gated by T02. Workspace removal requires separate approval (Q04).
- [T09](../tasks/T09.md): context/instruction snapshots and research reuse.

Exit: deterministic tests can demonstrate state safety, restricted sessions and safe
workspace creation/recovery without any autonomous product implementation workflow.

## Phase 3 — clarification and approved plans

- [T10](../tasks/T10.md): conversational read-only Orchestrator.
- [T11](../tasks/T11.md): read-only Planner and structured plan validation.
- [T12](../tasks/T12.md): immutable explicit plan approval gate.

Exit: a user can receive a useful plan; every backend execution path rejects absent,
stale or revoked approval. No automatic jump from a plan to implementation.

## Phase 4 — safe single-agent delivery

- [T13](../tasks/T13.md): one-slot scheduler and approved assignments.
- [T14](../tasks/T14.md): scoped execution and permission requests.
- [T15](../tasks/T15.md): frozen-content trusted verification.
- [T16](../tasks/T16.md): independent review and bounded corrections.
- [T17](../tasks/T17.md): local commit gates, default OFF and human-review overrides.

Exit: small disposable feature runs end to end with verifiable approvals, tests,
review and a correct commit/uncommitted result. Required checks cannot be waived by AI.

## Phase 5 — observable, recoverable MVP

- [T18](../tasks/T18.md): Reverb and durable timeline/catch-up.
- [T19](../tasks/T19.md): usage/budgets and retention.
- [T20](../tasks/T20.md): restart reconciliation, pause/cancel and backup/restore.
- [T21](../tasks/T21.md): dashboard and full native acceptance; split into the specified
  smaller UI/acceptance units before approval.

UI can be delivered incrementally after underlying contracts stabilize; do not wait
for a large final dashboard change. T21 is a release capability grouping, not permission
to implement all screens in one pass. Reverb dependency changes require approval.

Exit: PRD FR01-FR16 and failure scenarios pass on both native platforms; document exact
tested versions, remaining limitations and real usage. No remote hosting claims.

## Phase 6 — optional measured extensions

- [T22](../tasks/T22.md): justified parallel tasks, workspace integration and aggregate review.
- [T23](../tasks/T23.md): native subagent controls and accounting, only where proven.

Require separate approval after MVP. API-key sign-in, richer analytics, remote access
and multi-user support need future specs/tasks; they are not implicit scope here.

## Sequencing, complexity and resources

Follow task dependencies rather than assuming every phase can run in parallel.
Examples of potentially independent work after prerequisites: T07 and T08; T18 and
T19. Use one executor by default even where independent work exists. Parallel agents
need an approved scope and measurable benefit; never delegate just to fill slots.

S/M/L indicate relative complexity/uncertainty. Native isolation, persistent runner,
recovery and dashboard acceptance are L and must be split into coherent reviewed
increments when selected. Do not turn task counts into calendar promises. T01-T03
should produce measured protocol/startup/token observations to inform subsequent
per-task time/token ranges. Unknown cost stays unknown, particularly with ChatGPT
subscription access. Each task gets a displayed budget and bounded retry/repair policy.

## Definition of done for each implementation task

1. Explicit scope approval and dependency evidence are recorded.
2. Work occurs on a task feature branch with protected user changes.
3. Applicable instructions/skills/versioned docs are checked; code follows siblings.
4. Criteria and important failure paths have meaningful verification; run the narrowest
   relevant existing checks and required formatter/static/build checks for changed code.
5. Independent reviewer examines requirements, plan, exact diff and test results.
6. Commit policy is satisfied; no auto push/merge/deploy; memory reflects actual outcome.

Documentation approval does not enable automatic committing. Current planning files
are saved on `docs/orchestrator-product-plan` and remain uncommitted for user review.
