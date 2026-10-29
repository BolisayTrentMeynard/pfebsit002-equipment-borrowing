# Flutter Web Screen Flow (sketch)

```
Login -> role check -> Home (by role)

Borrower: My Requests (list) -> New Request (form) -> Request Detail
Approver: Pending Approvals (list) -> Request Detail (approve/reject) ; Manual Handling (failed notifications) ; Overdue list
Staff:    Approved Items (release + due date) ; Return & Inspection (form) ; Custody Summary
```

## Screens
1. Login
2. My Requests / Pending Approvals / Approved Items (same list widget, different filters)
3. New Request form
4. Request Detail with status timeline
5. Release form (staff)
6. Return and Inspection form (staff)
7. Overdue and Custody Summary
8. Manual Handling list (approver)

## Reusable widgets
`RequestCard`, `StatusChip`, `AppForm` fields with validators, `StateView` (shows loading, empty, error, unauthorized), `ConfirmDialog`.

## Form validation
Required fields, date must be in the future, max loan length. Server returns 422 with field errors, which the form shows under each field so form and server rules agree.

## API states to show
Loading spinner, empty list message, success snackbar, 422 field errors, 401 (back to login), 403 (not allowed page), network error with retry button, server error message without internal details.

## Responsive behavior
Desktop: list and detail side by side. Narrow (under about 700 px): single column, detail opens as a new page.
