# SiteEngine FieldEdge Connector 0.1.1 — DEV acceptance

Date: 2026-11-13. Canonical FieldEdge development branch: `codex/fieldedge-climatrol-081-20261106`.

## Package

- Distribution: `releases/0.1.1/siteengine-fieldedge-connector-0.1.1.zip`
- ZIP SHA-256: `33fd49a1e92ccf0f72299d6b03588e535a12b09a82b71fdaaaa93f5703a9bcac`
- Three exact ZIP members, all under `siteengine-fieldedge-connector/`, with POSIX `/` path separators. Source canonical files remain under `includes/connectors/fieldedge/`, and the standalone release copies match them byte-for-byte.
- WordPress plugin slug: `siteengine-fieldedge-connector`, version `0.1.1`. Requires FluentCRM and Ads Bridge. No front-end requests, cron, webhook, Google submission, real lead POST, or mail functionality.
- Installation/update tested through standard owner-scoped WP-CLI `wp plugin install <ZIP> --force --activate` on `climatrol.siteengine.space`; no duplicate plugin folders retained.

## Real provider read (separate from closed form-39 892/893 work)

- Existing Windows DPAPI OAuth bundle, one read-only token request, four FieldEdge GETs, all HTTP 200. The GETs were `customer_work_orders` and three specific `work_order` details, with no retries, POSTs, notifications or customer communication.
- Exact provider customer was independently confirmed against the user's CRM export; associated source WordPress form 39 entry 889 and CRM contact 334 were verified by matching email/created timestamp and a successful Fluent Forms `fluentcrm` delivery log.
- The three work-order responses returned `completed=true` and three explicit invoice UUID references. No new invoice GETs were made during this pass; a separate earlier bounded read verified one explicit work-order invoice detail.
- Provider responses are reduced to typed customer/work-order UUIDs, completion status/times, optional invoice references and SHA-256 digests. No raw customer records, email, addresses, monetary values, OAuth credentials or GCLIDs are committed or printed.

## WordPress / CRM acceptance

- Target: `https://climatrol.siteengine.space/`; version `0.1.1` **active**, site HTTP 200.
- The three FE custom-field definitions exist and have the expected text/textarea types.
- An explicitly pinned private payload was transferred with SHA-256 readback to a 0600 file outside WordPress webroot; exact pre-write FE custom-field state was backed up privately.
- CLI `siteengine-fieldedge apply` wrote the three allowed FE fields on FluentCRM contact 334. Three real work-order summaries persisted; completed count 3, invoice-reference count 3. Original email/subscription status were unchanged.
- Exact readback and repeat CLI invocation returned `replayed`, with no duplicate provider/CRM effect. Wrong SHA-256 was refused with exit code 2. Following a standard 0.1.1 ZIP upgrade, replay still passed.
- Ads Bridge inquiry table remained at zero rows for this historical contact/entry. No CRM Won/Lost transition, historical inquiry adoption, Ads export, customer message, or FieldEdge business write occurred.
- Prior form 39/entry 889 consent records were not used to establish attribution or export eligibility; no consent bypass or Google submission was performed.

## Automated test acceptance

- Live CRM projection: 29 focused pure assertions
- Bounded live snapshot: 12 pure assertions
- Historical lookup: 12 pure assertions
- Targeted GET/allowlist: 45 assertions
- OAuth transport: 43 assertions
- All selected suites PASS; new release PHP lint PASS; launcher PowerShell parser PASS.

## Explicitly incomplete / next work

This release delivers working **manual** production-read-to-dev-CRM synchronization. It does not claim automatic polling or client-ready tenant onboarding. Windows DPAPI remains the available secure OAuth host. Each client needs independent credentials/tenant mapping and onboarding. Lead creation via FieldEdge is disabled. Automatic Won/Lost and invoiced-value conversion require authoritative inquiry-to-specific-work-order association, policy and ad eligibility; customer/email and click ID proximity alone do not prove which of three jobs belongs to an inquiry. AB remains the sole owner of Google conversion export and inquiry/consent authority.

The archived original fieldedge provider discovery journals remain closed and untouched. Recovery evidence and the exact source/input are retained privately; only sanitized acceptance is version controlled.
