V20: Academic Office local test reset + external CSS/JS.

1. Back up local bcp_scheduling database.
2. Replace updated project files.
3. In phpMyAdmin choose local bcp_scheduling then run ACADEMIC_OFFICE_LOCAL_TEST_RESET.sql once if you want to erase ALL Academic Office test reports, approvals, and preview requests. This is irreversible.
4. Refresh browser Ctrl+Shift+R.
5. New requests should begin from #1 because the Academic Office request table was emptied and auto increment reset. This affects actual database IDs, not only labels.
6. This reset does not retire class/exam schedules; optional statements are included and commented out in SQL.
7. PHP externalization includes dashboard, reports, history, preview-review, document modal, and submit JS. Inline event-handler attributes that implement existing approval buttons have not been refactored, to minimize regression risk.
8. No schema migrations required.
