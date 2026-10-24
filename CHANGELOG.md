# Changelog

## 0.6.3 - 2026-10-23

- Persist an activation-bound reset plan and original field/lifecycle values before touching FluentCRM.
- Resume only unfinished original-to-intended changes; do not resubmit completed field deletions or tag changes.
- Persist a verified projection checkpoint before the inquiry pointer transaction. Pointer retries verify the checkpoint without repeating the reset.
- Fail closed on unexpected contact edits, configuration drift, corrupt plans and legacy activating rows without a recovery snapshot.
- Add inquiry schema 2 with three nullable recovery columns through explicit migration. Check physical column types/nullability and the stored version before adapter mutations; report incomplete migration in doctor/inspect and as a CLI migration failure.
- Add isolated interruption/recovery coverage and native SQL checkpoint/session-loss coverage. CRM Ops runtime and installed live inquiry acceptance remain outstanding.


## 0.6.2

- Serialized Ads Bridge adoption, prepare, activation and active-history synchronization with database/prefix/contact-scoped GET_LOCK locks; added owner verification and fail-closed contention/loss/reentrancy handling.
- Required prepare expected_head_version to match the stored active inquiry and commit head_version to advance exactly one step; activation rechecks the current head before projection.
- Reserved one pending reconciliation per contact through the serialized API and blocked competing initial adoption.
- Bound activation site identity to preparation and rejected invalid/overflowing versions and oversized event IDs.
- Made completed/archived activation replays safe without another contact reset; completed prepare replays report committed/already_activated with historical state/version.
- Required InnoDB and checked transaction start, guarded archive/activation writes, commit, rollback and final readback. Unconfirmed rollback/release closes the failed session and blocks further transitions in that request.
- Added 50 isolated transition/fault assertions and 12 dedicated-database assertions covering real cross-connection locks, rollback, retry, contact independence and connection loss. CRM Subscriber operations remain simulated in the database suite.
- Retained schema version 1, contract version 1 and both worksheet contracts. Partial-projection recovery and live CRM Ops integration remain held.

## 0.6.1

- Corrected repeat-inquiry resets to use FluentCRM selective blank-key deletion. Prior dates, revenue, reason, and export-state fields now actually clear.
- Suppressed Ads Bridge lifecycle timestamp restoration during an intentional inquiry reset and while that contact has an incomplete activation.
- Added fresh-model field/tag readback so silent projection failures cannot be acknowledged as committed.
- Verified the activating marker before performing the CRM reset and preserved prior active history from synchronization while activation remains incomplete.
- Cleared configured lifecycle/revenue field aliases as well as canonical fields; invalid mappings and unresolved/non-unique lifecycle tags fail before reset writes.
- Corrected Unqualified milestone clearing to delete a stale qualification value.
- Replaced merge-only test behavior with installed FluentCRM deletion semantics; inquiry acceptance now dispatches the real lifecycle and inquiry callbacks and includes projection-failure cases.
- Kept schema version 1, adapter contract version 1, and both worksheet contracts unchanged. Broader reconciliation hardening and live CRM Ops acceptance remain pending.

## 0.6.0

- Added durable inquiry-aware attribution persistence in `{prefix}se_ads_inquiries` so one FluentCRM contact can safely own multiple sequential advertising inquiries without overwriting historical form/revenue/lifecycle state.
- Added explicit one-time `adopt_current_inquiry` migration phase for pre-0.6 contacts. Repeat prepare fails closed with `previous_inquiry_not_adopted` instead of snapshotting a potentially overwritten contact `submission_id`.
- Added stable same-site CRM Ops adapter functions for adopt, prepare reconciliation, and activation commit. The handshake is a persisted prepare/activate/ack saga; CRM Ops must keep customer automation held until final Ads Bridge commit.
- Fluent Forms reconciliation sources now require explicit immutable `form_id` + `entry_id`; Ads Bridge validates the configured master form and computes `ff:{form_id}:{entry_id}` itself.
- Added unique inquiry, active-contact, transaction, reconciliation, prepare-event, and activation-event constraints for idempotency and one-active-inquiry-per-contact enforcement.
- Inquiry-aware Qualified, Unqualified lifecycle, and Converted exporters read immutable inquiry bindings; contacts with inquiry history no longer fall back to mutable contact-level submission linkage.
- Archived inquiries remain stage-export candidates until their reached milestones are ledgered, preventing a repeat-inquiry switch from starving a prior Qualified/Converted event between cron runs.
- Inquiry Google rows prefer user identity and attribution captured on the immutable Fluent Forms submission; current contact data is only a fallback for identity fields the original form did not capture. Current DNC remains authoritative at send/export time.
- Extended `All_Leads` from 93 to 94 columns by appending `Inquiry ID`; existing 93-column sheets migrate header-only and historical rows are untouched. `Google_Ads_Import` remains exactly 21 columns.
- Added `wp se-ads inquiry-admin migrate|adopt|inspect` for explicit schema administration, controlled adoption/testing, and diagnostics. Inquiry schema creation is never implicit.
- Preserved legacy single-inquiry operation for contacts without inquiry history so 0.6.0 can be deployed before CRM Ops repeat-inquiry activation.

## 0.5.2

- Fixed FluentCRM admin save-order race where a blank custom field from the same contact update could overwrite lifecycle milestone timestamps stamped by the tag-added hook. Lifecycle milestones are now reconciled again on `fluent_crm/contact_custom_data_updated`.
- `inspect-contact` now shows `qualified_at`, `unqualified_at`, and `customer_rating` for lifecycle diagnostics.
- Corrected Doctor guidance: `provision-sheets` is preview/read-only by default and does not accept `--dry-run`; use `--apply` only to write.

## 0.5.1

- Simplified the authoritative client lifecycle to `New`, `Qualified`, `Unqualified`, and `Won`.
- Added optional Lifecycle Compliance Automation: seed New on new master-form contacts, keep lifecycle tags mutually exclusive, stamp `qualified_at`, `unqualified_at`, and `closed_date` when appropriate, and never fabricate invoice revenue.
- Made `Won` terminal against accidental lower-stage additions; intentional reopening requires removing `Won` first.
- Made `Unqualified` clear a stale/unexported `qualified_at` milestone so a corrected bad lead cannot later emit a false Qualified Lead signal.
- Added a read-only Lead Compliance work queue and `wp se-ads compliance` command for missing/conflicting lifecycle state, stale New leads, missing Unqualified reason/timestamp, and incomplete Won records.
- Added `unqualified_at` to required operational field provisioning.
- Repurposed the configurable `customer_rating`-style field as Unqualified Reason with canonical values Wrong Area, Wrong Service, Wrong Product, Wrong Price, Spam / Junk, and Other.
- Retired the five `Lost_Wrong_*` worksheet contracts and the active Lost worksheet exporter. Unqualified now remains internal and intentionally produces no Google conversion.
- Standardized the workbook to two tabs only: `Google_Ads_Import` first and `All_Leads` second.
- Extended `All_Leads` to a 93-column append-only lifecycle ledger with Customer Rating, Qualified at, Unqualified at, Lifecycle event, and Lifecycle event at.
- Added independent All_Leads lifecycle sinks for Qualified, Unqualified, and Won snapshots so later CRM decisions are retained after the intake row has already been ledgered.
- Added direct New -> Won qualification preservation: Won stamps `qualified_at` when blank so a client who skips the intermediate Qualified action does not lose the Qualified Lead milestone.
- Preserved actual-revenue-only Google Converted export; invoice amount remains a manual business input.
- Added safe 0.5.0 -> 0.5.1 Sheet migrations and guarded normalization for the known manually edited Golden Sheet variants; manual columns are normalized only when affected data cells are blank.
- Preserved the 21-column Google import contract with separate `Conversion action` values for `SE | Qualified Lead` and `SE | Converted Lead`.

## 0.5.0

- Adopted the SiteEngine Golden Account conversion taxonomy: `SE | Lead`, `SE | Qualified Lead`, and `SE | Converted Lead`; future `SE | Qualified Phone Call` remains reserved for Call Bridge.
- Added a durable Qualified Lead stage driven by configurable `qualified_at` and a dedicated `export-qualified-leads` command.
- Split Qualified and Converted ledger scopes while preserving the same canonical `ff:{form_id}:{entry_id}` across lifecycle stages.
- Added a trailing `Conversion action` column to the shared `Google_Ads_Import` contract so Data Manager can filter Qualified and Converted rows from one first/leftmost worksheet.
- Added safe 0.4.1 worksheet migration: Google import 20→21 columns, All Leads 80→88, Lost diagnostics 20→29, with new columns appended only and historical data rows untouched.
- Added worksheet-index migration/verification so `Google_Ads_Import` is moved to index 0 without changing cell data.
- Reclassified Lost reason worksheets as internal diagnostics rather than Google bidding conversion feeds.
- Added Golden Account ValueTrack context (`se_cid`, `se_agid`, `se_adid`, `se_kw`, `se_mt`, `se_net`, `se_dev`, `se_loc`) to All Leads and Lost diagnostics.
- Made the canonical Fluent Forms submission the primary source for GCLID/GBRAID/WBRAID with FluentCRM fallback; GBRAID wins if both braid types are present.
- Fixed phone normalization so ordinary U.S. 10-digit / 11-digit values are converted to E.164 before SHA-256 hashing.
- Preserved Gmail plus aliases while retaining Google-style lowercase/trim/Gmail-period normalization.
- Added consent-gated browser-side `siteengine_ads_bridge_lead` dataLayer payload for website `SE | Lead` / Enhanced Conversions configuration; Ads Bridge still does not call Google directly.
- Added generated 10-minute cPanel cron guidance plus runtime freshness and PHP-version diagnostics.
- Removed fresh provisioning of the obsolete dedicated `lost_date` field; legacy configs remain readable.
- Extended ledger administration to recognize Qualified, Converted, and historical 0.4.x Google import sink scopes; historical 0.4.x Won rows suppress duplicate 0.5 Converted exports without suppressing the new Qualified stage.
- Preserved actual-revenue-only gating for Converted Lead; no synthetic fallback revenue is generated.

## 0.4.1

- Added an Ads Bridge-owned setup/reference tab to `SiteEngine -> Help / Reference`; Base supplies the documentation shell while Ads Bridge versions its own instructions.
- Added a direct setup-page link to the Ads Bridge Help / Reference section.
- Corrected `inspect-config` provenance labels in settings mode so plugin-owned values report `Ads Bridge settings` while shared client facts report `SiteEngine Base`.
- Kept the accepted 0.3.1 export/ledger/consent/Sheet engine unchanged.

## 0.4.0

- Integrated Ads Bridge with SiteEngine Base 2.2 as a registered module under the central SiteEngine UI.
- Added Base prerequisites for Business Name, Business Timezone, Currency, and Google Ads Customer ID.
- Added WordPress-backed schema-v2 settings with shared client facts supplied by SiteEngine Base.
- Added Setup, CRM & Lifecycle, Field Mapping, Exports, Google Sheets, Diagnostics, and Migration admin tabs.
- Added one-click import of existing plugin-local or external PHP configuration into WordPress settings.
- Preserved explicit external `SEAB_CONFIG_FILE` as the highest-precedence compatibility override until deliberately retired.
- Preserved plugin-local `config/client.php` as a migration fallback.
- Removed the activation requirement for a pre-existing PHP config; fresh installs activate safely with all exports disabled and report Setup Required.
- Declared SiteEngine Base as a WordPress plugin dependency.
- Made `closed_date` / Outcome Date the fresh-install default for both Won and Lost terminal dates. Existing configs retain their configured field.
- Updated Doctor to treat SiteEngine Base as the shared Business Data authority.
- Kept Google service-account credentials outside WordPress; the UI stores only the external credential-file path.
- Export, ledger, consent, reconciliation, and Sheet-write behavior remains the accepted 0.3.1 engine.

## 0.3.1

- Fixed Won discovery starvation: already-appended current transactions no longer consume the candidate limit during tag-based discovery.
- Fixed Lost discovery deduplication: prior lost exports no longer exclude a FluentCRM contact for life; dedupe is `lost_leads_sheet + current Transaction ID`.
- Enforced configured `fluent_forms.form_id` for deterministic Won/Lost submission resolution and restricted email fallback to that form.
- Made `exports.won_jobs.value_field` and `exports.won_jobs.date_field` authoritative at runtime, including eligibility and row construction.
- Preserved `ads_conversion_value_frozen` as the first value source when it contains a positive frozen value.
- Removed the active practice-client `config/client.php` from the distributable package. External `SEAB_CONFIG_FILE` remains fully supported; plugin-local config remains a backward-compatible fallback when deliberately created.
- Changed `config/client.example.php` to ship with all exports and Google Sheets disabled.
- Neutralized the legacy GeneratePress exact-value doctor guard so the package no longer embeds practice-site account identifiers.
- Expanded acceptance coverage for consent denial, DNC, failed-attempt retryability, transaction dedupe, candidate starvation, wrong-form submissions, configured Won fields, and sequential returning customers.
- Regenerated the release manifest from final package contents.

No database migration or ledger rewrite is required from 0.3.0.

## 0.3.0

- Added ledgered `export-all-leads` operational export.
- Added five reason-specific, consent-gated `export-lost-leads` feeds.
- Added `export-enabled` as the combined cron entry point.
- Added `provision-sheets` for exact tab/header creation, required-width grid expansion, and verification.
- Added dedicated `lost_date` and `submission_id` field provisioning.
- Added external `SEAB_CONFIG_FILE` support.
- Extended ledger reconciliation to All Leads and Lost Leads sinks.
- Made configured consent policy version authoritative while retaining runtime-policy diagnostics.
- Corrected uninstall behavior to preserve the append-only ledger and remove stale table names.
- Preserved the accepted won-job export behavior and HF-01 ledger-protected `append-row` adapter.

## 0.2.0 + HF-01

- Added Phase 4 ledger administration.
- Removed the `--force` re-append path.
- Routed `append-row` through the ledger-protected batch exporter and required `--apply`.
