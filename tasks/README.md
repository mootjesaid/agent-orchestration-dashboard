# Implementation task index

T01-T03 capability validation is approved and partially exercised; see
[measured results](../docs/CAPABILITY-VALIDATION.md). T04-T23 remain unapproved.
Task existence alone does not authorize execution.
See [roadmap](../docs/ROADMAP.md) for gates and [decisions](../docs/DECISIONS.md) for user input.
Task links and dependency order:

| Task | Objective | Depends on |
| --- | --- | --- |
| [T01](T01.md) | Protocol capability baseline | Explicit authorization |
| [T02](T02.md) | Native isolation and process proof | T01 |
| [T03](T03.md) | Native review suitability proof | T01, T02 |
| [T04](T04.md) | Existing starter and native runtime baseline | T01, T02, T03 |
| [T05](T05.md) | Durable workflow state and transitions | T04 |
| [T06](T06.md) | Command journal outbox and leases | T05 |
| [T07](T07.md) | Codex stdio runner and session lifecycle | T02, T06 |
| [T08](T08.md) | Repository registration and Git broker | T02, T05 |
| [T09](T09.md) | Context and instruction snapshots | T07, T08 |
| [T10](T10.md) | Conversational clarification coordinator | T05, T07, T09 |
| [T11](T11.md) | Read-only planning and plan validation | T10 |
| [T12](T12.md) | Immutable plan approval gate | T11 |
| [T13](T13.md) | Single-agent scheduling and assignment | T06, T07, T08, T12 |
| [T14](T14.md) | Scoped execution and permission requests | T13 |
| [T15](T15.md) | Trusted verification and frozen content | T14 |
| [T16](T16.md) | Independent review and correction loop | T03, T15 |
| [T17](T17.md) | Human and automatic local commit policy | T08, T12, T16 |
| [T18](T18.md) | Reverb and durable timeline delivery | T06, T07 |
| [T19](T19.md) | Usage budgets and history retention | T07, T13 |
| [T20](T20.md) | Restart reconciliation and recovery controls | T06, T07, T08, T14, T17, T19 |
| [T21](T21.md) | MVP dashboard and native acceptance | T10, T12, T16, T17, T18, T19, T20 |
| [T22](T22.md) | Parallel task workspaces and integration | T21 |
| [T23](T23.md) | Bounded native subagent integration | T21 |

Each approved task must have a feature branch, meaningful narrow verification, independent
review and a memory handoff. Capability tasks T01-T03 use disposable fixtures only and
return evidence; they do not authorize product functionality. Implementation tasks must
activate relevant project skills and verify package APIs before code changes.
