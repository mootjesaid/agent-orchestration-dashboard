# T01-T03 capability validation results

Date: 2026-11-18. User approved T01-T03 only. Results below are measured observations,
not product implementation or a claim that all safety gates passed.

## Environment and reproducibility

- Native Windows 11 Pro, version 10.0.26300, build 26300, 64-bit.
- Codex CLI 0.169.0, existing ChatGPT account authentication detected through
  `account/read`; no login/logout, credential copying or token refresh was requested.
- Codex executable SHA-256:
  `CE1945EB440B5481ED1233EBAF32B7056EE7E4E452FA62FFE1CBFB26103CDEE0`.
- Generated non-experimental v2 schema bundle SHA-256:
  `3532041BB3D56639A8E3FD41254DAFF6E3F0BE67893D585C7CEB0F57B1BFC57D`.
- Both default and `--experimental` JSON schemas generated successfully from this
  binary. This pins the experiment, not an approved permanent minimum version.
- Native PHP command resolves only to `C:\tools\php83\php.exe`, PHP 8.3.8. This
  differs from the project PHP 8.5 target. Locked Symfony Process 8.1.10 requires
  PHP >=8.4.1. The installed Laravel/Symfony runner cannot be validated on this
  initial native PHP baseline. The subsequent approved setup below resolves this.
- macOS testing deferred at the user's request on 2026-11-18. No Mac is connected;
  OS version/hardware and all native macOS results remain unknown.

Temporary schemas and sanitized JSON observations are under
`%TEMP%/orchestrator-capability-0169/`. The disposable PowerShell harness and sentinel
fixture are under ignored `storage/app/private/`: `capability-probe.ps1`,
`capability-fixture-0169/` and `capability-outside-0169.txt`. These are capability
experiments, not application functionality. They are retained for local inspection;
do not rely on ignored/temp artifacts as the only evidence. This report preserves
the outcomes. Never commit real account/configuration secrets with future fixtures.

The fixture repository was initialized on `capability-fixture`, with no commit,
remote, push or modification of the application's Git metadata. Protocol processes
ran with redirected stdio and no visible window. Only known probe process instances
were stopped. No global Git trust/configuration or Codex saved settings were changed.

## T01 — protocol and account baseline

| Check | Result | Limit of the evidence |
| --- | --- | --- |
| JSONL stdio initialize + initialized | PASS | One connection at a time, Windows only |
| Account metadata | PASS: account type `chatgpt` | Does not prove future entitlement, expiry or refresh recovery |
| Model discovery | PASS | Availability is account-specific and must be discovered again |
| Ephemeral read-only thread | PASS | Returned sandbox `readOnly`, network disabled, approval `never`; enforcement tested separately below |
| Read-only model turn | PASS: exact `CAPABILITY_OK`, completed | No repository files or tools requested |
| Thread resume | PASS: same thread in same process | Cross-process/crash resume not exercised |
| Activity and usage | PASS | Turn/item events and cumulative/last token usage observed |
| Server process exit | PASS: exit observed after closing stdin/cleanup | Does not establish descendant cleanup or crash recovery |

Discovered default model: `gpt-6.2-sol`; other reported models included
`gpt-6.2-astra`, `gpt-6.1-terra`, `gpt-6.1-sol`, `gpt-6-astra`, `gpt-6-sol`,
`gpt-6-luna` and older models. `gpt-6.2-sol` advertised low, medium, high, xhigh,
max and ultra effort. Do not hardcode this inventory into application policy.

The low-effort marker turn reported 12,900 input tokens, 7 output tokens and 12,907
total tokens; reported cached input, cache write and reasoning output were zero.
This measures one inherited-context session, not an intrinsic protocol overhead or
cost forecast. Context/configuration must be audited and reduced before repeated
role turns. Native review did not emit a usage observation captured by this probe;
its cost is unknown, not zero.

## Schema-derived adapter findings

These are observations of generated 0.169.0 schemas, not speculative wire APIs.

- `ThreadStartParams` exposes sandbox, approval policy, model, cwd, ephemeral and
  developer instructions. `TurnStartParams` exposes effort and `outputSchema`.
- `ReviewStartParams` requires threadId and target; delivery permits only `inline`.
  It does not expose an `outputSchema` property. `ReviewStartResponse` returns a
  turn and reviewThreadId. Create a fresh thread for independent review.
- Command/file/permission approval parameters include `startedAtMs` as a required
  field, along with session/item identity. Adapter fixtures must match the pinned
  schema rather than simplified documentation examples.
- `ServerRequest` includes command, file-change and permission approval methods.
  Their schemas were inspected; actual interactive approval routing was not exercised.
- Stable/default generation includes `command/exec` and `thread/shellCommand`.
  A method being present in the default schema is not a safety allowlist. Explicitly
  restrict transport calls and never expose arbitrary host commands to runtime roles.

## T02 — Windows sandbox primitive checks

Tests used explicit policies, PowerShell commands and sentinel files only. A separate
host read checked the actual files after every attempted write.

| Policy / operation | Exit | Filesystem observation | Result |
| --- | --- | --- | --- |
| readOnly: read sentinel | 0 | Original content readable/preserved | PASS positive control |
| readOnly: overwrite sentinel | 1 | Original content preserved | PASS denial |
| readOnly: create new file | 1 | File absent | PASS denial |
| readOnly: delete sentinel | 1 | Original file/content preserved | PASS denial |
| workspaceWrite: normal file | 0 | File created inside allowed fixture root | PASS positive control |
| workspaceWrite: overwrite fixture `.git/config` | 1 | Original Git config preserved | PASS denial |
| workspaceWrite: overwrite outside sentinel | 1 | Original outside content preserved | PASS denial |

Workspace-write checks disabled network and excluded implicit temp writable roots.
The first read-only attempt supplied `outputBytesCap=2048`; all commands were rejected
before execution with `custom outputBytesCap is not supported with windows sandbox`.
Those attempts are NOT denial evidence. Removing that field produced the execution
results above. Use host-side bounded stream/log storage on Windows; do not assume the
wire option works just because the generated schema includes it.

These checks do not prove protection for linked-worktree common metadata, all `.git`
paths, symlink/junction traversal, subprocess variations, network egress, MCP/plugins,
hooks, arbitrary user configs or orchestration credentials. The exact effective
Windows sandbox backend was not independently identified. No elevated sandbox setup
was performed. No permissions were broadened by the probe.

Still required: PHP 8.5/Symfony bidirectional stdio, process-tree termination,
interruption during a tool, persistent resume across process restart, stale permission
resolution, sleep/wake, linked-worktree attacks, bounded output/backpressure and full
tool/config isolation. Mac equivalents remain deferred. T02 is NOT complete.

## T03 — native independent review

A fresh ephemeral read-only thread received only an inline synthetic change:
`function isAdult(age) { return age > 18; }`. Requirement: age 18 must qualify.
Acceptance: 17 false, 18 true, 19 true. The prompt explicitly disclosed that no tests
had been run. No product repository source was supplied, and no tool activity was
observed in the review stream.

Native `review/start` completed. Its `exitedReviewMode.review` value was a **String**.
It identified the age-18 boundary defect as P2, supplied synthetic file/line evidence,
and correctly stated that automated acceptance evidence was missing. The filename
was supplied as inline review context; this does not prove filesystem diff discovery.

Outcome: native review is useful for findings, but this result does not satisfy a
machine-valid approval contract. Neither prose nor the absence of a native finding
can authorize commit. Proposed adapter direction: preserve native findings and obtain
a schema-validated independent review verdict/coverage where needed; otherwise route
to human review. T03 is partial: real uncommitted/base-branch diffs, passing fixtures,
untracked/binary changes, malformed/interrupted output and structured fallback remain
untested. Do not mark the review safety gate passed.

## Gate outcome and next work

### Subsequent PHP setup — prerequisite resolved

The user separately approved PHP 8.5 setup. Inspection found an existing php.new/Herd
Lite PHP **8.5.12** at `%USERPROFILE%/.config/herd-lite/bin/php.exe`; the initial
shell had selected the older machine PATH entry. No binary reinstall was necessary.
Created the previously absent `%USERPROFILE%/Documents/WindowsPowerShell/profile.ps1`
to prefer Herd Lite's bin in this user's PowerShell sessions. No system-wide PATH or
PHP 8.3 files were changed. Existing terminals need a restart or profile reload.
This preference does not alter a bare cmd.exe's system PATH ordering.

Verification from a fresh profile-loading PowerShell with normal machine+user PATH:

- `php -v`: 8.5.12; `composer --version`: Composer 2.8.12 using PHP 8.5.12.
- `php artisan --version`: Laravel Framework 13.43.0.
- `composer check-platform-reqs --lock`: all listed requirements pass.
- Both SQLite extensions present; reported SQLite library 3.45.2.
- Symfony Process 8.1.10 bidirectional asynchronous stdin/stdout: PASS, two messages
  (`PING`, then `PONG`), child exit 0, five-second bound, no source files written.

This resolves the PHP prerequisite, not the full Codex transport/lifecycle matrix.

Windows transport and basic sandbox primitives are promising. T01 has measured
Windows baseline evidence; T02 and T03 remain partial. No overall phase acceptance,
unattended execution enablement or cross-platform support claim is justified yet.

1. Native PHP 8.5 setup is complete with separate user authorization; retain this
   explicit runtime when resuming the remaining tests.
2. Finish T02's Windows isolation/process matrix against that runtime before product
   runner implementation. Expand linked-worktree and tool/config negative tests.
3. Finish T03 review fixtures and a validated verdict strategy. Keep it independent
   from any execution session.
4. Resume Mac validation when a native machine is available. This deferral is user
   approved; it does not waive release FR16.

No application functionality, agents/orchestration code, dependency changes, package
installation, application commit, push, merge or deployment was performed.
