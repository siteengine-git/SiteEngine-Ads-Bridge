> Current source: **0.6.3.1-rc.1, unaccepted maintenance candidate**. Requires
> Consent0.6.1-rc.2 at1c0a420 (current eligibility result contract2). Historical
> grant verification alone no longer permits advertising. Read
> [maintenance release notes](docs/MAINTENANCE-RELEASE.md) before upgrade.
> Accepted installable0.6.3 remains preserved; no deployment is implied.

# SiteEngine Ads Bridge 0.6.3

SiteEngine Ads Bridge connects a configured Fluent Forms master lead form to FluentCRM and exports append-only lead lifecycle data to Google Sheets for Google Ads Data Manager.

Google Ads API upload remains disabled. Google Sheets are the only advertising-data sink.

## Lifecycle model

The client-facing lifecycle is intentionally small:

- `New`
- `Qualified`
- `Unqualified`
- `Won`

The business still makes the judgment. Ads Bridge can optionally handle the bookkeeping around that judgment.

When Lifecycle Compliance Automation is enabled:

- new contacts created by the configured master Fluent Form receive `New` when no lifecycle state already exists;
- lifecycle tags are mutually exclusive;
- `Qualified` stamps `qualified_at` when blank;
- `Unqualified` clears an unexported/stale `qualified_at` milestone and stamps `unqualified_at` when blank;
- `Won` stamps `qualified_at` when blank, because a sale is necessarily a qualified lead;
- `Won` stamps `closed_date` when blank;
- `Won` remains terminal if a lower-stage tag is accidentally added while Won is still attached; remove Won first to intentionally reopen/correct a record;
- invoice revenue is never fabricated or inferred.

The default Unqualified Reason field is the existing FluentCRM custom-field slug `customer_rating`. Canonical values are:

- Wrong Area
- Wrong Service
- Wrong Product
- Wrong Price
- Spam / Junk
- Other

## 0.6.2 version and transition hardening

0.6.2 enforces the stored Ads inquiry head version at prepare and activation, permits only one pending reconciliation per contact through the supported API, and requires activation to advance the expected head by exactly one. It serializes adoption, prepare, activation and active-history synchronization with connection-scoped database locks. A failed lock acquisition returns retry before the transition mutates the contact.

The activation site identity must match preparation. Completed activation replays, including archived ones, acknowledge the earlier event without resetting the current inquiry. A prepare replay after completion returns `committed / already_activated` with its stored inquiry state and committed version, never a new `ready` authorization.

Inquiry pointer changes require InnoDB, checked transaction control, guarded one-row updates and post-commit readback. Unconfirmed rollback or lock release closes the failed database session and prevents further transitions in that PHP request. See `docs/CRM-OPS-INQUIRY-CONTRACT.md` for response handling.

The 0.6.1 selective field deletion and lifecycle guards are retained. The adapter contract remains 1 and the worksheet contracts are unchanged. Version 0.6.3 requires explicit migration to inquiry schema 2. No event listeners for the proposed CRM Ops reconciliation events are registered.

## 0.6.3 durable reset recovery

Before resetting a contact, activation stores the original relevant fields/tags, the intended reset, its configuration and activation identity, and a SHA-256 integrity hash in the inquiry row. It then applies only unfinished changes. After fresh persisted-field/tag verification, it stores a completion checkpoint before committing the inquiry pointers.

Retry uses the identical activation body through the existing PHP adapter. A fully completed reset is verified without clearing fields or changing tags again. A partial reset resumes only values that still match the saved original or intended result. Unexpected field/lifecycle values, changed mappings or damaged plans stop activation for review. Legacy `activating` rows without a saved plan cannot be reconstructed safely and are held for review.

Inquiry schema **2** adds three nullable recovery columns. Install alone does not migrate the database. Preview and apply the existing `inquiry-admin migrate` command explicitly; the adapter refuses new transitions until both the physical columns and recorded version pass verification. Legacy exports continue during this migration gate. No Sheet provisioning is needed.

CRM Ops runtime is not implemented. Start New Inquiry and inquiry email sequences are planned features, not settings in this plugin. Before those features are built and accepted, CRM Ops must provide its atomic head compare-and-swap, durable holds and coordination of current-inquiry edits. The contact reset still occurs before the inquiry-table transaction; this is not an atomic transaction across CRM Ops, FluentCRM and Ads Bridge. No background recovery worker, cancellation or automatic compensation is provided. See `docs/CRM-OPS-INQUIRY-CONTRACT.md` for the recovery procedure and limits.

## Inquiry-aware repeat customers (introduced in 0.6.0)

0.6.0 removes the assumption that one FluentCRM contact equals one lifetime advertising inquiry. FluentCRM remains the editable current-contact/current-inquiry projection, while Ads Bridge owns immutable attribution history in `{prefix}se_ads_inquiries`.

The Google contract does **not** change. Each form inquiry still uses `ff:{form_id}:{entry_id}`. A returning customer can therefore have independent inquiry transactions such as `ff:3:37` and `ff:3:54` while remaining one FluentCRM contact.

Key rules:

- existing 0.5.x contacts continue in legacy single-inquiry mode until adopted;
- once a contact has inquiry history, inquiry rows own Qualified/Converted/lifecycle exports and the exporter never falls back to that contact's mutable `submission_id`;
- active and archived inquiries remain eligible for a stage they reached but have not yet exported;
- the canonical form binding, click IDs, consent provenance, and form-captured identity come from the immutable inquiry submission;
- current DNC state remains a poison pill at export time;
- CRM Ops owns `se_crm_current_inquiry_id`; Ads Bridge does not create a competing inquiry identifier.

### Upgrade adoption gate

Before CRM Ops enables **Start New Inquiry** for a contact that already existed before 0.6.0, it must durably adopt that contact's current inquiry into Ads Bridge. This closes the migration race where a later Fluent Forms feed could otherwise overwrite the contact-level `submission_id` before old attribution was frozen.

Stable same-site adapter functions:

- `seab_inquiry_adopt_current( $payload )` — one-time upgrade/current-inquiry adoption
- `seab_inquiry_prepare_reconciliation( $payload )` — prepare the proposed next inquiry
- `seab_inquiry_commit_activation( $payload )` — commit Ads Bridge after CRM Ops activates its head

CRM Ops should call these PHP adapter functions from its trusted same-site integration layer. It must not write Ads Bridge tables directly and does not need loopback REST calls.

The cross-plugin protocol is a persisted saga: **adopt (upgrade only) -> prepare -> CRM Ops activates -> Ads Bridge commit/ack**. Customer-facing automation remains held until the final Ads Bridge response is `status=committed`.

For `source.type=fluent_form`, reconciliation must include immutable `form_id` and `entry_id`; Ads Bridge validates the configured master form and computes the Google transaction ID itself.

The inquiry schema is never created implicitly. Preview/apply explicitly:

```bash
wp se-ads inquiry-admin migrate
wp se-ads inquiry-admin migrate --apply
```

Use `wp se-ads inquiry-admin inspect` for read-only diagnostics. `inquiry-admin adopt` is available for controlled migration/testing; normal CRM Ops adoption should use the stable adapter.

## Google conversion model

The standardized Google conversion actions are:

- `SE | Lead` — online/browser success event
- `SE | Qualified Lead` — offline qualification milestone
- `SE | Converted Lead` — offline won sale with actual invoice value
- future `SE | Qualified Phone Call`

`Unqualified` is intentionally **not** uploaded as a Google conversion. The absence of a downstream Qualified event is the negative optimization signal. The internal reason is preserved in `All_Leads` for SiteEngine/client analysis.

The canonical transaction/order ID remains:

`ff:{form_id}:{entry_id}`

The same transaction ID may appear once per conversion action because Qualified and Converted are distinct Google actions. Ads Bridge uses independent ledger sink scopes for each stage.

## Two-tab workbook

The workbook remains intentionally limited to two tabs:

1. `Google_Ads_Import`
2. `All_Leads`

`Google_Ads_Import` is forced to worksheet index 0 because Google Ads Data Manager reads the first/leftmost worksheet from a Google Sheets source.

The five historical `Lost_Wrong_*` worksheets are retired.

### Google_Ads_Import

Canonical 21-column schema:

1. Conversion date/time
2. Transaction ID
3. GCLID
4. GBRAID
5. WBRAID
6. Email address
7. Phone number
8. IP address
9. First name
10. Last name
11. City
12. Postal code
13. State or region code
14. Country code
15. Conversion value
16. Currency code
17. Ad measurement consent
18. Ad personalization consent
19. User agent
20. Session attributes
21. Conversion action

Qualified and Converted rows share this tab. Configure Google Data Manager filters using `Conversion action`:

- `Conversion action = SE | Qualified Lead`
- `Conversion action = SE | Converted Lead`

Do not separate stages using `Conversion value > 0`; Qualified Lead may later use a positive proxy value.

### All_Leads

`All_Leads` is an internal append-only operational/lifecycle ledger. 0.6.0 has 94 columns: the prior operational record, Golden Account attribution context, lifecycle context, plus:

- Customer Rating
- Qualified at
- Unqualified at
- Lifecycle event
- Lifecycle event at
- Inquiry ID

A deterministic lead may therefore produce multiple internal snapshots over time:

- `New`
- `Qualified`
- `Unqualified`
- `Won`

Each lifecycle stage uses a separate ledger sink, so later client decisions are retained even after the original intake row has already been appended.

DNC and consent status are recorded in `All_Leads` but do not suppress the internal operational ledger. They remain advertising-export gates where applicable.

## Client compliance

The WordPress admin includes **SiteEngine -> Ads Bridge -> Lead Compliance**.

This is read-only. It flags common client-maintenance failures:

- no lifecycle state
- conflicting lifecycle states
- stale `New` leads
- Qualified leads missing `qualified_at`
- Unqualified leads missing reason or `unqualified_at`
- Won leads missing invoice value, close date, or qualification timestamp

CLI equivalent:

`wp se-ads compliance`

The purpose is to make client compliance visible without guessing whether a lead was good or bad.

## Required FluentCRM operational fields

`wp se-ads provision-fields` verifies/provisions:

- `invoiced_amount` — number
- `closed_date` — date
- `qualified_at` — date_time
- `unqualified_at` — date_time
- `submission_id` — text
- `ads_export_status` — text
- `ads_exported_at` — date_time
- `ads_conversion_value_frozen` — number
- `ads_export_error` — textarea

The Unqualified Reason field (`customer_rating` by default) is an existing business field and is not created automatically by `provision-fields`.

## Consent

Advertising exports require authoritative SiteEngine Consent provenance. `ad_user_data=granted` is the primary export gate. `ad_personalization` is represented independently.

The plugin does not infer consent from a generic CRM status.

## Hashing and identifiers

Ads Bridge hashes user identifiers before Sheet output where required.

- Email: trim/lowercase; Gmail/googlemail periods removed; plus aliases are preserved.
- US 10-digit phone: normalized to `+1XXXXXXXXXX` before SHA-256.
- Explicit international E.164 intent is preserved.
- If both GBRAID and WBRAID exist, GBRAID is emitted and WBRAID is blanked.
- Canonical Fluent Forms submission attribution wins over CRM fallback values.

## Golden Account ValueTrack context

The operational ledger preserves these optional tracking parameters when the form captures them:

- `se_cid={campaignid}`
- `se_agid={adgroupid}`
- `se_adid={creative}`
- `se_kw={keyword}`
- `se_mt={matchtype}`
- `se_net={network}`
- `se_dev={device}`
- `se_loc={loc_physical_ms}`

## Safe Sheet migrations

`wp se-ads provision-sheets` is dry-run by default.

0.6.0 recognizes:

- canonical 0.4.1 20-column Google import header and appends `Conversion action`;
- canonical 0.5.2 93-column `All_Leads` and appends `Inquiry ID`;
- canonical 0.5.0 88-column `All_Leads` and appends the five lifecycle-ledger headers plus `Inquiry ID`;
- legacy 80-column `All_Leads` and appends Golden tracking, lifecycle headers, and `Inquiry ID`;
- the known Golden-template manual compact Google header where `Conversion action` temporarily replaced `Session attributes`;
- the known manually edited Golden `All_Leads` tail containing Customer Rating / Qualified at.

Manual variants are normalized only when the affected trailing data cells are confirmed blank. Otherwise provisioning fails closed rather than reinterpreting existing data.

Historical data rows are never deleted or rewritten by normal Sheet provisioning.

## Export commands

Read-only / dry-run surfaces:

- `wp se-ads inspect-config`
- `wp se-ads doctor`
- `wp se-ads compliance`
- `wp se-ads inspect-contact --id=<contact_id>`
- `wp se-ads inspect-contact-submissions --id=<contact_id>`
- `wp se-ads inspect-contact-fields --id=<contact_id>`
- `wp se-ads conversion-dry-run --id=<contact_id>`
- `wp se-ads export-dry-run --id=<contact_id>`
- `wp se-ads provision-fields`
- `wp se-ads provision-sheets`

Write surfaces require explicit `--apply` where applicable:

- `wp se-ads provision-fields --apply`
- `wp se-ads provision-sheets --apply`
- `wp se-ads export-all-leads --apply --limit=25`
- `wp se-ads export-lifecycle-ledger --apply --limit=25`
- `wp se-ads export-qualified-leads --apply --limit=25`
- `wp se-ads export-batch --apply --limit=25`
- `wp se-ads export-enabled --apply --limit=25`
- `wp se-ads ledger-admin ...`
- `wp se-ads inquiry-admin migrate|adopt|inspect ...`

`export-lost-leads` is retained only as a compatibility command name and returns an explicit retirement error.

## Scheduler

Canonical scheduler entry point:

`wp se-ads export-enabled --apply --limit=25`

Recommended cadence: every 10 minutes.

Execution order:

`All_Leads intake -> lifecycle ledger -> Qualified Lead -> Converted Lead`

## Ledger sinks

Active ledger sinks:

- `all_leads_sheet`
- `all_leads_lifecycle_qualified`
- `all_leads_lifecycle_unqualified`
- `all_leads_lifecycle_won`
- `google_ads_import_qualified_lead`
- `google_ads_import_converted_lead`

Historical `google_ads_import_sheet` and `lost_leads_sheet` rows remain recognizable to ledger administration for migration/history. New exports do not write Lost worksheet rows.

## Configuration authority

Normal configuration is managed through SiteEngine Base + Ads Bridge settings. Secrets belong outside the web root, normally under `/home/<cpanel-user>/.secrets/`.

A PHP config file remains an explicit override/fallback mechanism but is not required for normal onboarding.

## Release verification

Run from the plugin directory:

```bash
php tests/static-acceptance.php
php tests/config-settings-acceptance.php
php tests/static-acceptance-040.php
php tests/static-acceptance-050.php
php tests/static-acceptance-051.php
php tests/static-acceptance-060.php
php tests/lifecycle-acceptance.php
php tests/inquiry-acceptance.php
php tests/inquiry-transition-acceptance.php
```

All PHP files should also pass `php -l` before packaging.
