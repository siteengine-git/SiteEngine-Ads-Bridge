# Inquiry acceptance status - 0.6.3

CRM Ops runtime remains unimplemented. Start New Inquiry and inquiry customer-email sequences are planned features, not current Ads Bridge settings.

## Server evidence supplied by Aubrey

- Installed 0.6.1 passed 18 lifecycle and 43 inquiry isolated assertions on the Golden server's PHP 8.4.
- Installed 0.6.2 passed 50 isolated transition assertions on the server. Its WordPress aggregate export dry-run completed all four groups without writes. The known legacy records remained blocked for their existing missing dates/revenue or authoritative consent defects.
- No live adoption, prepare or activation has been run in this continuation. The last observed Ads inquiry table had zero rows. An internal controlled fixture (site-specific identifiers omitted) remains the known valid-consent fixture with no CRM Ops inquiry ID assigned.

## Implemented in 0.6.3

- Freeze the original reset field/lifecycle snapshot, intended projection, configuration and activation identity in a durable hashed plan before any CRM reset.
- Resume partial resets by sending only unfinished original-to-intended changes. Verify fully completed work without another field/tag write.
- Store a verified completion checkpoint before the inquiry pointer transaction. Retry pointer failures without repeating the CRM reset.
- Withhold success on unexpected current values, configuration drift, corrupt plans, missing checkpoints or legacy activating rows whose original reset cannot be reconstructed safely.
- Explicit inquiry schema 2 adds three nullable recovery columns. Check physical column types/nullability and the recorded version before new adapter transitions. Migration must verify those columns/version before CLI success.
- The contract remains version 1 with the same three PHP functions. All_Leads remains 94 columns and Google_Ads_Import remains 21. No automatic schema/Sheet migration or new event listener is registered.

## Evidence and practical limits

See 0.6.3-acceptance.txt for exact local results. The isolated Subscriber doubles follow the selective deletion semantics supplied from installed FluentCRM 3.2.6 and invoke the real Ads Bridge lifecycle callbacks. Subscriber persistence remains simulated.

The dedicated SQL suite uses MariaDB 10.11.14 / InnoDB and two independent PDO connections. It covers additive recovery-column SQL, real row preservation, contention, checked pointer commit/rollback, durable plan/checkpoint visibility, partial-reset resumption and loss/replacement of the lock-owning session. It does not bootstrap WordPress dbDelta or installed FluentCRM. All database tests use a dedicated acceptance database, never the Golden database.

Local PHP is 8.3.6. Version 0.6.3 still needs installation, isolated PHP 8.4 validation, explicit Golden schema migration and WordPress regression verification. Live repeat-inquiry acceptance remains outstanding.

## Remaining work before live repeat activation

1. Build CRM Ops inquiry identity/head creation, adoption-before-replacement, atomic head compare-and-swap, durable event/phase/outbox persistence and the matching committed-ack automation hold.
2. Coordinate all permitted current-inquiry edits and form projections with the hold. Named locks serialize Ads Bridge calls, not arbitrary external FluentCRM writes. The CRM reset and inquiry pointer commit are not one shared transaction.
3. Decide and implement any required abandoned-reservation cancellation, operator conflict recovery or compensation workflow. This release safely resumes the same activation; it does not supply these operations or a recovery worker.
4. Run installed FluentCRM/live acceptance for old Won -> new inquiry, unexported old stages, retries/crash cases, immutable identity/consent, DNC suppression and independent stage ledgers on the host runtime/database.

Passing isolated/native SQL suites does not establish end-to-end CRM Ops readiness. The Golden CRM starter-data importer identity is still unknown.
