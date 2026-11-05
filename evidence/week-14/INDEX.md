# Week 14 Evidence Index: Secure Data Exchange and Encryption

Status: design drafted and code built on 2026-11-05. Not yet run or tested by the developer unless stated in the test matrix (to be written in the evidence round). Raw captures (screenshots, terminal output) are kept on Google Drive and listed here once sorted.

## Design (Part 1)
| File | What it is |
|------|------------|
| [`scope.md`](scope.md) | Which exchanges are protected, chosen controls, out of scope |
| [`diagrams/data-flow.png`](diagrams/data-flow.png) (source `data-flow.mmd`) | Data-flow diagram with trust zones and numbered flows F1 to F8 |
| [`protection-requirements-matrix.md`](protection-requirements-matrix.md) | Data, protection need, threat, control |
| [`decision-record-003-message-protection.md`](decision-record-003-message-protection.md) | HTTPS only vs HMAC vs authenticated encryption |
| [`key-lifecycle-plan.md`](key-lifecycle-plan.md) | Generate, store, use, rotate, revoke |
| [`acceptance-criteria.md`](acceptance-criteria.md) | AC-15 to AC-24 |
| [`test-data-plan.md`](test-data-plan.md) | Fake data, keys and capture hygiene |
| [`week14-failure-predictions.txt`](week14-failure-predictions.txt) | Predictions to commit before running the failure tests |
| [`residual-risk-register.md`](residual-risk-register.md) | Known limits and decisions |

## Code
`backend/app/Services/MessageSigner.php`, `SignedHttpNotifier.php`, `backend/app/Http/Controllers/Api/NotifierReceiverController.php`, `backend/config/notifier.php`, `backend/app/Console/Commands/NotifierTamperDemo.php` and `GenerateNotifierKey.php`, tests in `backend/tests/Feature/SignedMessageTest.php`. Flutter default API address: `app/lib/config.dart`.

## Still to do
Test matrix, captures, Activity 1 and Activity 2 write-ups, interpretation and maintainability note, final INDEX after the evidence round.
