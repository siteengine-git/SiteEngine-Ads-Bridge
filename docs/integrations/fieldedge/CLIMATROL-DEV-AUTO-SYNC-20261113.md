# Climatrol DEV scheduled FieldEdge polling — November 13, 2026

**Worker:** `tools/fieldedge/Invoke-Climatrol-Dev-Sync.ps1`.

**Target:** owner-scoped `https://climatrol.siteengine.space/`, site owner `clima6647`.

**Windows host:** Dell-8940, current-user DPAPI encrypted OAuth bundle. No credentials copied to WordPress.

## Registered scheduling

Windows Task Scheduler task `SiteEngine-FieldEdge-Climatrol-Dev-Sync` was registered **enabled/Ready** for six-hour intervals. Principal: existing workstation `admin`, InteractiveToken; it can run only with that Windows session available, and is **not a 24/7 server service**. Multiple instances are prevented; execution is capped at four minutes and there is no automatic retry inside an execution.

## Actual bounded processing

- A pinned privately retained provider customer/form/contact association is checked; source is form 39 entry 889 and CRM contact 334. This source is a *customer binding*, not a claim that any particular job resulted from entry 889.
- Each run creates a unique immutable private evidence file and a new locked, fsynced request journal. It performs at most one OAuth token request and four GETs (one customer-work-orders page and three exact work-order details). List pagination beyond the three selected jobs refuses rather than silently dropping results.
- Only source SHA256, provider/customer/work-order identities, vendor completion boolean/time and explicit invoice reference are sent to the WordPress site. No raw provider body, monetary value, customer email/address or OAuth token is transferred.
- Windows transfers the minimal payload using existing trusted SSH, then the canonical owner-scoped WP runner stages a 0600 file **outside the document root** and uses `siteengine-fieldedge apply` with an exact SHA256.
- The WordPress plugin validates exact original form-email/CRM-contact identity, schema, tenant and customer ownership. It writes only `se_fe_identity`, `se_fe_customer_json`, `se_fe_jobs_json`. Same input replays; changed economic facts that would roll back completion, remove an invoice or revise completion time hold.
- No provider POST, email/SMS, CRM Won/Lost action, historical inquiry adoption, Google advertising submission or export occurs.
- Failures are held and independently journaled without an in-run retry. Completed reads are retained for operator recovery.

## Verified facts and limitations

- Pure worker DryRun: PASS, no provider or WordPress requests.
- Actual first worker read: one OAuth + four FieldEdge GETs, HTTP 200; private summary of three jobs persisted. Its initial receiver call failed due uppercase run-ID characters in the filename. The runner now lowercases the destination filename, and the retained successful provider snapshot was recovered and applied on Climatrol DEV without repeating the GETs; WordPress returned `replayed`.
- Two subsequent freshly journaled OAuth attempts returned `https_transport_failed` before any business GET. DNS and TCP/443 to both provider hosts were confirmed. No uncontrolled retry occurred. The next **scheduled** run will make at most one new token request after the six-hour interval; results cannot be promised in advance.
- The **end-to-end scheduled execution** has not yet produced a standalone successful task history entry. Do not represent the scheduled task as proven reliable. Current CRM data is independently verified and preserved.
- This is a *single-customer Climatrol DEV poller*, not a marketed general-tenant onboarding or 24/7 production synchronization service. The native WordPress connector 0.1.1 is versioned and packaged; automated customer-wide polling, FieldEdge leadId-to-workOrderId continuity, approved economic policy, CRM Won/Lost and Google advertising reporting remain separate work.

## Safety / recovery

Old form39 892/893 discovery and the earlier 334 historical-pass journals stay closed. Each new run has a unique evidence hash, journal, sanitized failure or completion receipt, and no implicit replay. Retry from a closed failed OAuth pass is prohibited; a later independent scheduled run has a new identity. A previously successful provider snapshot can be explicitly recovered by its retained hash and staged again without provider calls.

Scheduled task definition can be inspected using Windows `Get-ScheduledTask -TaskName SiteEngine-FieldEdge-Climatrol-Dev-Sync` and `Get-ScheduledTaskInfo`. Do not add real lead POSTs, webhook registrations, messages or advertising submissions to this task.
