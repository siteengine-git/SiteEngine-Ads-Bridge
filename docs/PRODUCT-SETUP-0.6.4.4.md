# Ads Bridge 0.6.4.4 — portable setup correction

Upgrade the plugin files normally; no reactivation, automatic schema migration,
record rewrite or settings resave is required. Keep delivery disabled until separate
account/feed/credentials/scheduler acceptance. Base and WordPress are not changed.

The diagnosed defect was in lifecycle current_time('mysql') writes (site wall time)
and CLI build_terminal_datetime/build_google_ads_import parsing (conversion wall
time). A retained nonpersisted UTC/New York probe shifted an instant by five hours.
This establishes a parser defect, not corruption of any historical customer record.
Configuration disagreement and the unconditional Google Ads reminder are separate.

New Ads qualified/unqualified milestones include an explicit UTC offset. Existing
naive vendor datetimes are interpreted as WordPress site-local wall time, then
converted to the effective conversion timezone. Explicit offsets preserve instants;
date-only outcomes remain business dates in the effective conversion zone with the
selected time-of-day. DST gaps, invalid dates and ambiguous naive fall-back values
are refused rather than normalized. No historical values are rewritten. Schema-2 inquiry DATETIME snapshots cannot
retain an offset; their naive site-local fold values remain blocked for review
rather than acquiring a guessed instant. No schema/projection change is included.
Previously
written naive values whose originating timezone changed or is unknown require
operator investigation; today's timezone cannot recover their original offset.
This release does not invent a new timestamp source for third-party/custom fields.

Normal administrator workflow:
1. Setup displays the effective account and conversion timezone. Inspect that exact
   Google Ads account's Admin > Account settings. Enter the matching IANA zone and
   explicitly record manual confirmation. It is never labelled an automatic check.
   Account or effective conversion-zone changes invalidate the fingerprint. Explicit
   external configuration keeps its precedence; no authority migration is implied.
2. CRM & Lifecycle selects existing optional source titles (Referral is valid), or
   Unused. Required lifecycle selectors retain their existing distinct-ID checks.
3. Field Mapping previews the existing provision-fields schema. The explicit Create
   Missing CRM Fields action uses the same vendor logic as CLI provision-fields.
   Missing unqualified_at is date_time. Existing definitions/values are preserved;
   duplicate slugs/type conflicts stop the write for CRM-owner review. No blanket
   replacement, automatic activation provision or contact mutation is performed.
4. Field Mapping > Submission ID Feed records explicit operator inspection of
   {submission.id} -> submission_id. It binds the master form, mapped selectors,
   form definition and saved feed metadata. Changes invalidate it. Optional exact
   contact/submission IDs add readback of stored linkage/form membership only;
   they do not establish consent, identity, every feed or future behavior. No new
   fixture, fallback/latest search or export is invoked by this setup action.
5. Exports > Converted Conversion Action owns exports.won_jobs.conversion_action.
   Qualified and Converted are distinct events sharing Google_Ads_Import. One
   Doctor row per family is expected; card/notice/Diagnostics are separate displays,
   not evidence of duplicate rows or delivery. No duplicate transport defect found.
6. Google Sheets diagnoses missing path, missing file, present but unreadable/nonfile,
   and inaccessible/unknown parent separately where determinable. Compare PHP SAPI,
   UID and scheduler context. No secret contents or filesystem permissions are read
   into reports/changed. Do not enable exports to clear disabled-state warnings.
7. Setup supplies quoted version-specific CLI PHP and WP-CLI paths. Schedule every
   ten minutes only after separately authorizing/enabling exports. Doctor reports
   disabled, enabled-never-successful, stale, failed or incomplete attempt separately.
   Aggregate telemetry does not replace per-row ledger review or prove delivery.

Module failures use one actual Doctor result set for names, reasons, count and
corrective destinations; external authority no longer masks Doctor failures.
Existing Consent signature/current-eligibility, independent personalization, DNC,
retry/identity gates and Sheet contracts are retained. No Google Ads API upload.

Rollback: restore only backed-up plugin files under the existing executor. Preserve
Ads settings/operator-verification option, inquiries, Consent barriers and journals.
Older code may ignore the new manual records and show reminders again. Do not
restore an entire database, change Base facts or purge audit/history tables.
