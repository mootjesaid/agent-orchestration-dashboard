# Decision register

Updated 2026-11-18. Confirmed means the user chose it; proposed means awaiting design
approval. This document never grants implementation permission.

## Confirmed

| ID | Decision | Source / consequence |
| --- | --- | --- |
| D01 | Native Windows and native macOS in release one | User reply 2026-11-17; WSL/Docker cannot be prerequisites |
| D02 | Existing ChatGPT Codex sign-in first | User reply; API-key integration deferred |
| D03 | One local user, multiple repositories | User reply; remote/multi-user access deferred |
| D04 | Automatic commits OFF by default | User reply; task human-review override always enforced |
| D05 | Investigation/design/documentation only now | Original assignment; installation/implementation requires new approval |
| D06 | Laravel owns orchestration; agents supply reasoning/work | Original requirements; no dashboard state engine |
| D07 | Planner read-only, Orchestrator never implements, review independent | Original requirements; permissions must enforce role boundaries |
| D08 | T01-T03 capability validation approved | User replied "approve" to the recommended bounded next phase on 2026-11-18; T04+ and installation remain unapproved |
| D09 | Defer native macOS validation | User requested Mac checks later on 2026-11-18; no cross-platform acceptance claim until tested |
| D10 | PHP 8.5 setup authorized and completed | User approved setup on 2026-11-18; existing Herd Lite PHP 8.5.12 selected through user PowerShell profile, no reinstall/dependency change |
| D11 | T05 approved with T04 deferred | User asked to start T05 and explicitly deferred T04 on 2026-11-18. The remaining capability gates still apply to runtime work. |
| D12 | T06 approved on main | The user approved T06 and directed work on main for this task. The remaining capability gates still apply to runtime work. |
| D13 | Standalone executor clones and Laravel-controlled Git for the first release | The user accepted the Q03 design. Enable unattended execution only after the workspace write boundary is proven. |
| D14 | Implement the trusted local broker while executor writes stay disabled | The user approved the broker slice. Native OS isolation and executor integration keep their existing gates. |
| D15 | Finish the T02 Windows milestone with macOS validation deferred and continue T08 | The user approved continuing both tasks on 2026-11-18. Cross-platform release validation and enforced executor isolation still apply. |

## Proposed architectural decisions

The Phase 1 Windows probes support native review as supplemental evidence and
an independent schema-constrained turn for machine verdicts. Human review
remains the fallback for incomplete evidence. Q02 and Q03 still gate unattended
execution. See [capability results](CAPABILITY-VALIDATION.md).

| ID | Proposal | Why / alternative |
| --- | --- | --- |
| P01 | Extend existing Laravel 13/Inertia 3 starter | Existing code and official installation guidance; do not reinstall |
| P02 | PHP runner with Codex stdio, short DB-queue jobs | Durable control without Windows PCNTL dependence; assess Node bridge only if PHP I/O fails |
| P03 | Separate top-level role sessions/processes initially | Permission separation; process pooling only after measurement and proof |
| P04 | Native review first with validated structured verdict | Reuse Codex; fallback independent read-only turn if native output insufficient |
| P05 | SQLite/local disk plus outbox and Reverb | Small local deployment; no Redis/Horizon requirement |
| P06 | One global active model turn/executor slot in MVP | Lower cost and simpler recovery; parallel task execution later |
| P07 | Trusted Git broker and per-task workspaces | Reviewable changes and permission controls; worktrees alone do not enforce isolation |
| P08 | Capability/security gates before workflow features | Prevent a dashboard being built around unsupported guarantees |

## Decisions still requiring user input

These do not block documentation. Resolve at the indicated gate; recommended values
are proposals, not recorded consent.

| ID | Question | Recommendation | Needed before |
| --- | --- | --- | --- |
| Q01 | Resolved: next scope approved | T01-T03 capability validation only (D08) | Approval recorded 2026-11-18 |
| Q02 | macOS OS/hardware selection deferred (D09) | Windows probe host is build 26300; choose Mac versions when hardware is available | Cross-platform T02 completion and release claims |
| Q03 | Workspace design selected in D13. Which enforceable isolation policy can protect it? | The focused Windows writes were denied, but the tested native policy allowed an outside secret read and loopback access. An enforceable read and network boundary is still required. | T02/T08 |
| Q04 | Where may task workspaces be created and how long should they remain? | Dedicated user-selected local root; keep dirty workspaces until explicit cleanup approval | T08 |
| Q05 | Token/time budget, concurrency and review-loop defaults? | One active turn; two correction rounds; require displayed per-workflow budget; collect usage before numeric token defaults | T13/T19 |
| Q06 | Must commits be signed; should repository hooks run? | Preserve user signing policy; inspect hooks, isolate execution and revalidate content | T17 |
| Q07 | History/log retention and sensitive-source exclusions? | Keep plans/approvals/review history; configurable sanitized raw-log retention, proposed 30 days; exclude secrets by default | T09/T19/T20 |
| Q08 | How should local UI access be provisioned? | Reuse starter authenticated UI; explicitly configure a local account, disable public onboarding only in an approved auth task | T04 |

Do not ask questions answerable through repository inspection. Revisit a confirmed
decision only if new evidence creates a concrete incompatibility or the user changes it.
