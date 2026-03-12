# Pages scheduling QA checklist

1. **Backfill existing records**
   - Sign in to the CMS and visit `modules/pages/backfill_schedule.php` once (or run it via `curl` while authenticated).
   - Confirm the JSON response reports `"success": true` and note how many records were updated.

2. **Verify scheduled publish UI flow**
   - Open the Pages module, edit an existing page, enable **Published**, and set **Publish at** to a future timestamp (e.g., +10 minutes).
   - Save the page and confirm the listing now shows a “Scheduled” badge with the scheduled time, and the modal fields retain the same values when reopened.
   - Log out (or use a private window) and attempt to load the page URL; it should return a 404 while scheduled.

3. **Verify active publish window behaviour**
   - Adjust the same page so **Publish at** is in the past and **Unpublish at** is blank.
   - Save and confirm the status badge switches to “Published” with an optional “Unpublishes…” note only when an end time is set.
   - Visit the public URL logged out and ensure the page renders normally.

4. **Verify scheduled unpublish flow**
   - Set **Unpublish at** to a timestamp a few minutes in the future (leave **Publish at** blank).
   - After the timestamp passes (or edit the JSON to a time in the past), refresh the Pages list to confirm the badge changes to “Expired” with the timestamp and the page returns 404 when logged out.

5. **Confirm sitemap respects schedules**
   - Trigger `modules/sitemap/generate.php` and ensure scheduled (future) or expired pages are excluded from the `entries` array while currently live pages remain.

6. **Regression checks**
   - Toggle publish status via the “Publish/Unpublish” action and verify schedule values persist.
   - Duplicate a page and confirm the copied entry inherits the schedule settings and displays the correct status.
