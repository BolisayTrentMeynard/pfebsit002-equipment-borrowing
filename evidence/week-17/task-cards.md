# UAT Task Cards (Week 17)

Read the **Say** text word for word. Do not explain the app. Item names in [brackets] are filled in from `test-data-plan.md` after chunk 1. "Success" is for the facilitator only; do not read it out.

| Card | Role | Say | Success (facilitator only) | Operator action | Safe behavior expected |
|------|------|-----|----------------------------|-----------------|------------------------|
| UAT-01 | Borrower | "You need a projector for a class for three days. Use this app to ask to borrow one." | A new request for [available projector] with 3 days and a reason appears in the borrower's list as pending | None | A required field left empty is refused with a message |
| UAT-02 | Approver | "Some requests are waiting for you. Approve the one for [item A]. Turn down the one for [item B] and tell the borrower why." | Request A approved; request B rejected with a note | None | Only waiting requests can be acted on |
| UAT-03 | Staff | "The approved item is ready to be handed over. Record that the borrower has taken it." | The request is released with a due date | None | An unapproved request cannot be released |
| UAT-04 | Approver | "Your office wants to know whether any equipment is late coming back. Find out which item is late and who has it." | Tester names the overdue item and its borrower | None | Nothing overdue shows an empty-state message, not an error |
| UAT-05 | Staff | "A borrower has brought an item back. Record that it came back and that it is in good condition." | Return recorded with result good; request closed; the borrower sees it closed | None | An inspection result is required |
| UAT-06 | Borrower | "Try to make a request with a very short reason. Then try one with a loan length that is too long. Then try to request an item you already have an open request for. For each, tell me what the app told you." | For each, the tester reports the app's message and what they would change | None | Each is refused with a message that names the problem; nothing is created |
| UAT-07 | Any | "Sign in with a wrong password on purpose. Then sign in correctly. Then sign out." | Wrong password refused with a generic message; correct sign-in works; sign-out returns to the sign-in screen | None | The message does not say whether the e-mail exists |
| UAT-08 | Approver | "Some reminders could not be sent to borrowers. Find out which, and record what you did about it, for example that you phoned the borrower." | The failed notification is found and marked handled with a note | Before the card: make one reminder fail permanently (worker started with a wrong `NOTIFIER_URL`); prediction already committed | The item is listed once; marking it handled needs a note; the list is empty afterwards |
| UAT-09 | Borrower | "Keep using the app to look at your requests." | Tester reads the message shown, says what they will do, presses Try again after the operator restarts, and the list loads | Stop the API (Ctrl+C in its window) while the tester is on a list; restart it after the tester has reacted | A message, not a blank or frozen screen; the tester stays signed in; no data is lost |
| UAT-10 | Borrower | "Do the same as the first card, with this window made narrow." (Operator sets the window to about 360 px wide.) | A request is created at narrow width | Resize the window | Nothing is cut off or unusable |

## After the session: five questions (1 = strongly disagree, 5 = strongly agree)
1. I could do my tasks without help.
2. The messages told me what to do next.
3. I always knew where I was in the app.
4. I would be comfortable using this for real equipment, with real training.
5. Anything I wanted to do that the app could not do (free text; recorded as possible change requests).

Ratings are recorded per role code. With 3 testers they are illustrations, not statistics.
