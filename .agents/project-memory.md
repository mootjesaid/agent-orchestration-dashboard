# Project memory

Updated: 2026-11-18. Phase 1 Windows follow-up recorded. Native isolation exit remains blocked. T06 is implemented, independently reviewed and verified.

## Product

AI Development Orchestrator: a local Laravel-controlled development workflow engine
with a React/TypeScript/Inertia dashboard. Natural-language request -> clarification
-> read-only planning -> explicit plan approval -> execution -> independent review
-> optional local commit. Dashboard business decisions belong in Laravel.

## User-confirmed decisions

- Native Windows and native macOS in the first release; WSL is not a prerequisite.
- Existing ChatGPT sign-in for Codex first; API keys deferred.
- Single local user, multiple registered repositories.
- Automatic commits OFF by default; task-level human review always wins.
- User approved T01-T03 capability validation on 2026-11-18 after the planning handoff.
  Disposable protocol/isolation/review experiments are authorized; product functionality,
  dependency changes and later tasks require separate approval. The user subsequently approved T05 and deferred T04 for it. The user separately approved
  PHP 8.5 setup on 2026-11-18; the existing installation was configured successfully.

## Repository facts

An existing Laravel React starter is present. `composer show --direct` reports
Laravel 13.43.0, Inertia Laravel 3.7.0, Boost 2.10.4, Wayfinder 0.1.23 and PHPUnit
12.5.44. Symfony Process is 8.1.10. JS declarations include React 19, Inertia 3,
Tailwind 4, TypeScript 5 and shadcn configuration. No dependency changes were made.
There was no `.ai/rules` directory during investigation. Existing Boost instructions
and `.agents/skills` were preserved. Docker setup exists, but it is not the proposed
native host runtime for Codex. Reverb is not installed yet.

## Architecture direction (proposed, not implementation approval)

Laravel policy/state engine + short durable queue jobs + a long-lived PHP runner
owning Codex stdio + SQLite + Reverb read models. Separate read-only Orchestrator,
Planner and Reviewer sessions; executor writes only to its task workspace. Native
review first, subject to validation. Native subagents deferred until policy and
concurrency accounting are proven. Independent top-level sessions remain available.

## Important limitations

Initial documentation research has now been supplemented by approved Windows probes;
see docs/CAPABILITY-VALIDATION.md. Codex CLI 0.169.0 and generated schemas were measured.
Basic sandbox and native review checks passed within their limited scope. Full role
isolation, process recovery and PHP runner checks remain incomplete. No Mac was tested;
the user explicitly deferred Mac validation. Worktrees are not security sandboxes.
Subscription usage is not an exact dollar bill.

## Navigation and next action

- Requirements: `docs/PRD.md`
- Evidence and risks: `docs/FEASIBILITY.md`
- Design: `docs/ARCHITECTURE.md`, `docs/ORCHESTRATION.md`
- Decisions requiring input: `docs/DECISIONS.md`
- Gates and task index: `docs/ROADMAP.md`, `tasks/README.md`

The Phase 1 Windows follow-up verified persistent thread resume across App Server
processes, active-turn interrupt, a read-only subprocess write denial, PHP 8.5.12
stdio and bounded output, and stop of a synthetic process tree. Native review
found tracked and untracked fixture defects. Structured reviews returned the
expected passing, failing and incomplete verdicts. T02 still blocks unattended
execution pending full role/Git isolation, native Codex lifecycle and deferred macOS
validation. See docs/CAPABILITY-VALIDATION.md for exact limits. T04 is deferred
for T05. T08 has approval for the trusted local broker slice only.

The user selected standalone executor clones and Laravel-controlled Git (D13).
The Windows disposable clone probe denied both Git config writes and allowed a
write outside the clone. Executor write enablement remains blocked pending an
enforced workspace boundary.

T02 Windows Sandbox optional-feature setup returned DISM 3010 with
`/NoRestart`. A host restart is pending. The ignored disposable mapping
probe is staged at `storage/app/private/t02-windows-sandbox.ps1`.

On `feature/t08-git-broker`, Artisan can prepare a standalone local clone at an
exact commit and inspect its tracked diff and untracked paths. The required
workspace root is supplied by the trusted local caller. Windows PHP 8.5.12
passed Pint, PHPStan and all 67 tests with 277 assertions. T02, durable
registration and executor integration remain open.
T05 is committed on `main`. The user approved T06 and directed work on `main`.

## Completed planning deliverables

AGENTS.md and .agents memory; PRD, FEASIBILITY, ARCHITECTURE, ORCHESTRATION,
DECISIONS and ROADMAP documents; tasks/README.md and T01-T23 task specifications.
The roadmap separates capability gates, durable foundation, planning/approval,
single-agent delivery, observable/recoverable MVP and optional parallelism.
Every task includes dependencies, scope, acceptance criteria and validation evidence.
T21 is explicitly split into smaller UI/release units before implementation approval.
Open user decisions Q01-Q08 are in docs/DECISIONS.md; the three earlier user choices
remain confirmed. No installation, functionality or executable agent config was added.

Documentation checks: all local Markdown links resolve; 23 task files are present;
all task dependency IDs exist; tracked diff whitespace check passes. Generated skills
remain ignored while AGENTS.md and the two memory files are eligible for versioning.
No application tests were run. Approved disposable probes now exist in ignored
storage/app/private/capability-probe.ps1; no product functionality was added.

## Measured capability update (2026-11-18)

Codex 0.169.0 on native Windows 11 Pro 26300: generated stable/experimental schemas,
initialized stdio, detected ChatGPT account, discovered models (default gpt-6.2-sol),
completed no-tools marker turn and same-process resume. Marker usage: 12,900 input,
7 output tokens; inherited context needs reduction. Read-only read/create/overwrite/
delete and workspace-write normal/Git-config/outside sentinel operations were tested.
Reads/normal writes passed; protected writes denied and original files preserved.
Custom outputBytesCap is unsupported with Windows sandbox; retries omitted it.
Fresh native review identified an inline boundary bug, returned a prose String and
noted missing tests. It is not a structured commit verdict or full diff-review proof.
Full process/isolation/review matrices and PHP runner remain unverified. Sanitized
results/schema bundles: %TEMP%/orchestrator-capability-0169/. Durable report contains
environment, binary/schema hashes and result limitations.

## PHP 8.5 setup (2026-11-18)

The user explicitly approved PHP 8.5 setup. Re-fetched Laravel's agent instructions
and inspected the php.new Windows 8.5 installer, but did not need to execute it:
PHP 8.5.12 already existed at
C:/Users/MohamedSaidFactifBV/.config/herd-lite/bin/php.exe. Machine PATH placed
C:/tools/php83 first. Created the previously absent user all-hosts PowerShell profile
at C:/Users/MohamedSaidFactifBV/Documents/WindowsPowerShell/profile.ps1 to prepend
Herd Lite's bin for this account's PowerShell sessions. Machine PATH and PHP 8.3
files were preserved; no runtime binary, Composer package or Laravel app was installed.
User should reopen PowerShell (or dot-source the profile) for an existing terminal.
This is PowerShell configuration, not a system-wide cmd.exe PATH change.

Verified from a fresh profile-loading PowerShell with reconstructed machine+user PATH:
php resolves to 8.5.12; Composer 2.8.12 uses it; php artisan --version reports Laravel
13.43.0; composer check-platform-reqs --lock passes. pdo_sqlite/sqlite3 are available;
reported SQLite is 3.45.2. A bounded no-file-write Symfony Process 8.1.10 probe passed
two-way asynchronous stdin/stdout (PING then PONG, exit 0). This is not full Codex
runner, process-tree cleanup, sandbox or recovery validation. No PHP source file was
modified, and no application tests/migrations/dependency installation were run.

## T05 workflow state (2026-11-18)

The user approved T05 and deferred its T04 dependency. Implemented the SQLite
workflow, immutable request revision and audit tables, plus a version-guarded Laravel
action for creation, clarification, amendments and entry into planning. Approval
and execution belong to later tasks. On Windows PHP 8.5.12, Pint and eight focused
PHPUnit tests passed (32 assertions), including rollback and a two-connection SQLite
version race. Native read-only Codex follow-up review reported no remaining
findings. macOS validation remains deferred.

## T06 command journal, outbox and leases

Entering planning now stores command intent and outbox publication state in the
workflow transaction. A bounded SQLite sweep publishes commands and flags expired
or superseded commands for reconciliation. A generation can be replaced only
after trusted process-stop confirmation. T07 owns native process verification and
wire dispatch. Windows PHP 8.5.12 verification passed Pint, PHPStan and all 62
Laravel tests with 249 assertions. See tasks/T06.md for review and limitations.
