# Chat reliability — 2026-08-31

Project: /home/newadmin/plan/newPlanai
Site: https://static.hakimisadr.com
Pre-change backup: /var/www/backup/static-chat-fix-20260831-5LdW5b/before-chat-fixes.tar.gz
Existing unrelated/user changes were preserved; no database migration or server restart.

## Behavior

- New conversation/navigation cancels old browser requests and ignores late results.
- Structured SSE protocol v2 has meta, delta, notice, error and done events. Legacy clients keep the raw-text protocol; refresh an open tab once.
- Automatic provider fallback is disclosed in the UI, and successful answers store the actual model key.
- Draft text uses per-user, per-conversation sessionStorage, expires after 24 hours, and is not stored in the new error log. Attachments cannot survive a login/page reload and must be reselected.
- Only confirmed failed messages can be retried using retry_message_id. The server verifies ownership, text and latest-message status; a short cache lock prevents concurrent retries. No automatic browser POST replay occurs.
- Partial/disconnected upstream responses are not saved as successful answers or charged to site quota. Success persistence and quota changes share a database transaction.
- HTTP session/auth, quota, validation, upload-size, busy and server errors are displayed separately, with login/plan/review actions.
- Upstream transient failures retry at most once before any answer text. Retry-After up to 5 seconds is respected; longer waits and billing/oversize/auth errors are not retried on the same route. Total upstream deadline: 150 seconds. Browser deadline: 180 seconds.
- At most 80 recent history messages are loaded. A conservative 22,000-byte text/estimated-image input budget retains the system prompt and current input, dropping old turns only with a visible notice. Oversized current inputs are rejected with guidance, never silently truncated. Database history is not deleted.
- Word DOCX is read using bounded ZIP/XML parsing instead of constructing a PHPWord document. Corrupt, entity-bearing or oversized files get validation errors. Legacy DOC must be converted to DOCX/PDF.
- Text uploads: max 240,000 bytes. DOCX main XML: max 8 MiB, expanded archive: 64 MiB, entries: 2,000. Images: max 16 megapixels, resized before upstream transfer.

## Diagnostics

Daily files: storage/logs/chat-errors-YYYY-MM-DD.log
Retention: 14 daily log files, managed by the rotating logger.
Fields: request_id (shown as an 8-character prefix in the UI), model, stage, partial, exception class, HTTP status and cURL errno.
No prompt, API key or upstream raw body is written to this log.
Nginx access logs remain useful for 401/403/419/422 counts.

## Regression tests (no production database writes / no paid API calls)

Run from the project directory:

    vendor/bin/phpunit --no-configuration --bootstrap tests/chat-reliability-bootstrap.php --do-not-cache-result tests/Unit/ChatReliabilityTest.php
    python3 tests/chat-upstream-fixture.py

The JavaScript suite exports runChatReliabilityTests(source) from tests/chat-reliability.test.cjs.
Run it in Node with public/js/chat.js as the source argument, or in a JavaScript test runner.
The upstream fixture listens only on loopback on an ephemeral port and shuts down when finished.

Verified at release: 10 PHP unit tests (24 assertions), 9 JavaScript state/stream tests, 9 real-cURL loopback scenarios; PHP syntax and Blade compilation.

## Rollback

The tar backup contains the original six modified production files, including pre-existing edits.
Before restoring, compare current changes and preserve any newer work. Restore only these scoped files; do not reset Git or restore the whole project/database.
New helper and test files can remain unused after reverting the original files.
