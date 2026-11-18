# Technical feasibility and evidence

Investigated 2026-11-17. Verdict: feasible in principle; native isolation and process
recovery must pass capability gates before unattended execution is enabled.
This is documentation research and repository inspection, not a working prototype.

Update 2026-11-18: user-approved T01-T03 probes now provide measured Windows
protocol, sandbox-primitive and native-review evidence. See
[CAPABILITY-VALIDATION.md](CAPABILITY-VALIDATION.md). The investigation record below
describes the earlier documentation phase; its untested statements are historical,
not a claim that no probes have subsequently run. Full gates remain incomplete.

## Evidence classifications

**Observed** means local files or a read-only command established it. **Documented**
means an official source describes it. **Proposed** means our design choice.
**Unverified** means the pinned binary or target platform must still be tested.
Documentation pages can move and differ from installed releases; refresh them at T01.

## Existing project and setup

Observed: `artisan`, `app/`, `resources/js/`, migrations, auth/settings tests and
Composer/NPM lockfiles already exist. `composer show --direct` reports Laravel
13.43.0, inertia-laravel 3.7.0, Boost 2.10.4, Fortify 1.42.0, Wayfinder 0.1.23,
Pint 1.32.3 and PHPUnit 12.5.44. `composer show symfony/process` reports 8.1.10.
JS declarations: React ^19.2, Inertia ^3, Tailwind ^4 and TypeScript ^5.7; exact
installed JS versions must be checked against the lockfile during baseline validation.
shadcn configuration and shared UI components exist. Reverb is not a direct dependency.

The official [Laravel agent setup instructions](https://laravel.com/for/agents)
were fetched. They direct agents to detect an existing Laravel application and skip
installation when present. For a genuinely empty directory they currently prescribe
the React/SQLite/Boost starter and platform prerequisite checks. Re-fetch before any
future fresh setup; do not execute installation or global Cloud tooling in this phase.
[Laravel's release policy](https://laravel.com/framework/docs/13.x/releases) lists
13 as current and PHP 8.3–8.5 supported. Retain the installed 13.x baseline, with
PHP 8.5 as the project target; verify all locked dependencies before changing versions.

Boost MCP was used for version-scoped queue/process/broadcasting documentation.
[Boost](https://laravel.com/framework/docs/13.x/boost) supplies development guidance,
skills and documentation/tools; it is not the runtime workflow scheduler. Its broad
tools must not be exposed wholesale to read-only runtime agents. Existing Boost MCP
configuration runs via Docker; that configuration does not establish a native Codex
host setup. Preserve it pending the approved native-development setup task.

## Codex documented integration surface

The official [App Server guide](https://learn.chatgpt.com/docs/app-server) documents
bidirectional JSON-RPC-style JSONL over stdio; WebSocket transport remains experimental.
It provides initialization, persistent threads, turns, interruption, model/effort
discovery, approval requests, activity events, token usage and ChatGPT/API-key login.
CLI-generated schemas match the generating binary. Native `review/start` supports
uncommitted/base-branch/commit/custom targets. Current guidance uses a fresh thread
for separate review and returns review text. Experimental process-control methods
run outside the sandbox. These capabilities are documented, not locally exercised.

Proposed: use stdio; validate every wire message against a schema pinned in T01;
block undocumented or experimental methods by default. No browser-to-Codex socket.
Keep Laravel in control rather than treating turn completion as workflow success.
Do not depend on a remembered `detached` review option from older protocol versions.

## Native subagents and role isolation

[Official subagent guidance](https://learn.chatgpt.com/docs/agent-configuration/subagents)
describes delegation, custom model/effort configuration, concurrency settings and
additional token consumption. Crucially, local subagents inherit parent sandbox,
approval settings and MCP servers; custom role instructions do not create a separate
read-only sandbox. This rules out relying on a read-only prompt for a Planner spawned
inside a writable parent. The guide does not establish the complete external lifecycle
control and policy accounting this product needs.

Proposed: separate top-level role sessions, no hidden delegation in MVP. T23 measures
whether native delegation can meet global concurrency, stop, permissions, usage and
audit requirements. Use it only where verified; otherwise Laravel schedules separate
sessions. Never build a duplicate internal Codex conversation engine.

## Review suitability

The [official review guide](https://learn.chatgpt.com/docs/code-review) documents
native review workflows. Native review is a useful starting point, but does not by
itself prove our acceptance criteria, testing evidence, independent context or
machine-valid commit gate. Proposed: run native review in a fresh restricted role
session, supply the approved evidence bundle, then validate a structured verdict.
T03 evaluates output completeness and parsing. If native output cannot meet the
contract, use an independent read-only review turn; retain native findings as evidence.
Malformed, interrupted, truncated or missing findings mean inconclusive, never pass.

## Cross-platform process and filesystem risk

[Windows sandbox documentation](https://learn.chatgpt.com/docs/windows/windows-sandbox)
describes native PowerShell operation and several sandbox implementations with
different requirements and limitations. Native support exists, but equivalence with
macOS isolation must not be assumed. T02 must prove effective permissions and cleanup
on the selected OS versions and supported Codex binary. No weaker silent fallback.

[Symfony Process](https://symfony.com/doc/current/components/process.html) supports
argument arrays, asynchronous operation and streaming stdin. Proposed: assess the
already-installed component for a persistent bidirectional runner before adding a
Node bridge. Its suitability for high-volume Windows stdio, process-tree termination
and long waits is unverified. A Node helper is a contingency requiring a reviewed
architecture/dependency decision, never the orchestration authority.

Laravel [queues](https://laravel.com/framework/docs/13.x/queues) require PCNTL for
job timeouts, and worker timeouts must be shorter than reservation expiry. Proposed:
short queue jobs plus a separate deadline-aware runner; do not park an active Codex
conversation in a normal long-running job. Native Windows correctness must not rely
on POSIX worker signals. [Processes](https://laravel.com/framework/docs/13.x/processes)
documents asynchronous output and faking, useful for integration boundaries.

## Persistence, events and Git

[SQLite WAL](https://sqlite.org/wal.html) permits readers alongside a writer, but still
has only one writer at a time and is unsuitable for a shared network filesystem.
Proposed: local disk, short transactions, batched telemetry, tested busy timeout and
backups; avoid row-lock assumptions. SQLite version/security fixes must be checked at
baseline validation. Upgrade storage only with measured need and user approval.

[Reverb](https://laravel.com/framework/docs/13.x/reverb) provides WebSocket broadcasting.
Proposed: private workflow channels after durable writes; an ordered database timeline
and catch-up endpoint supply recovery. A WebSocket event is never durable authority.
Native Windows/macOS Reverb startup and shutdown remain release tests.

[Git worktree documentation](https://git-scm.com/docs/git-worktree) establishes linked
worktrees with separate working directories/indexes and shared repository metadata.
Proposed: per-task feature branches/worktrees with repository-wide operation locks.
Worktrees do not isolate OS access or protect shared Git metadata. Enforce writes to
Git through a trusted broker and OS permissions, not shell command string matching.

## Risk register and required proof

| Risk | Impact | Mitigation / gate |
| --- | --- | --- |
| Documentation/binary drift | Invalid calls or unsafe fallback | T01 pins version, generated schema and capability matrix; reject incompatible upgrade |
| Planner/Orchestrator writes via shell, MCP or child | Violates core role contract | T02 deny tests across tools; disable mutating MCP, plugins and background hooks; fail closed |
| Executor can mutate shared Git metadata or invoke Git over shell | Unauthorized commit/push/branch damage | T02/T08 prove OS restriction and trusted Git broker; no autonomous execution if enforcement fails |
| Native review output is prose/incomplete | False auto-commit eligibility | T03/T16 validated verdict tied to immutable diff; human review on uncertainty |
| Lost stdio/process crash/sleep | Duplicate turns or orphan writers | T07/T20 leases, fencing, command journal, reconciliation; never blind retry |
| Windows pipe/deadline/process-tree behavior differs | Stuck UI, unsafe cleanup | T02/T07 real native tests; interrupt before scoped termination |
| SQLite contention / queue expiry | Duplicated jobs or dropped events | T05/T06 short writes, constraints, CAS transitions, outbox and deduplication |
| Untrusted instructions, configs or MCP | Exfiltration/escalation | T02/T09 explicit trust review; minimal environment and tool allowlist |
| Worktree/base changes after approval | Approved plan no longer applies | T08/T12 recheck revision, base, instructions and scope before execution |
| Review/test-to-commit race | Commit contains unreviewed content | T17 exclusive writer stop, final content digest and compare before committing |
| Usage snapshots counted repeatedly | False token/cost totals | T19 reconcile cumulative values; separate estimated money from subscription usage |

## What this investigation did not establish

No App Server was started; no protocol schemas were generated; no agent was invoked;
no native review, permissions or recovery integration was run; no macOS testing took
place. A local Codex version probe failed with sandbox EPERM, so the installed CLI
version remains unknown. This is not evidence that Codex itself is incompatible.
Composer emitted sandbox ownership/Xdebug warnings but returned dependency versions.
Git inspection used a command-scoped safe-directory value; no global Git trust changed.
No packages were installed, tests run or application code changed in this phase.

The first approved work should resolve T01–T03. A failed security or portability gate
requires a revised design and user decision, not a workaround that weakens guarantees.
