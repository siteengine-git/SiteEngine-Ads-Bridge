# Ads Bridge 0.6.4.1 — actionable setup reporting

This reporting-only correction preserves stable 0.6.4 validation, configuration
precedence, coordination/recovery, Consent enforcement and disabled delivery.
No settings are saved and no missing mappings are invented by this change.

The Ads screen lists every structural validation failure, including on a fresh
unsaved setup. Each item has a readable field label and an action. Business Name,
Business Timezone, Currency and Google Ads Customer ID link to Base Business Data.
Ads identity, Consent policy and form mappings link to Ads setup; lifecycle, exports
and Sheets errors link to the appropriate Ads tab. Module-status messages include
all failures as plain text; structured setup_errors also carry the exact key/URL.
Rendering escapes all error strings. Doctor failures still prevent Ready.

Base runtime get() and explicit has() are distinct. A fallback timezone/currency
does not prove its Business Data prerequisite has been saved. Reporting keeps that
distinction without changing Base or relaxing either plugin's validation.

An explicit SEAB_CONFIG_FILE remains authoritative over saved Ads settings and
Base's effective Ads values. The screen and module message explain that UI resaves
cannot replace it. Migration provides the existing review/import path; importing
does not automatically retire an override. No configuration file is changed.

For the diagnosed client dev case, saved Base timezone, Base shared API/presence
and effective Ads timezone were valid America/Chicago. All four declared shared
prerequisites were present. Actual complete validation errors instead identified:

- fluentcrm.tags.lifecycle.new
- fluentcrm.tags.lifecycle.qualified
- fluentcrm.tags.lifecycle.unqualified

The active external v2 configuration and saved settings both lacked these three
mappings. Won remained present. No tags/mappings were created or guessed. The
operator must choose the intended existing lifecycle mappings in the authoritative
configuration, or deliberately migrate/retire the override. Re-saving timezone
does not repair these errors. No Base save/lookup defect was demonstrated.

Fresh local checks: 30 reporting assertions (actual Ads resolver/admin, synthetic
WordPress/Base/Doctor), 10 existing settings resolver assertions, 19 bootstrap and
85 static/package assertions; four affected PHP lints. No full acceptance campaign,
browser/fixture replay, transport or schema operation. Installed diagnosis used the
existing scoped CLI with its unrelated incompatible CRM bootstrap excluded only
for those read-only invocations; it is not an installed UI test of this correction.
Detailed target/version/errors and the CLI compatibility limit stay in local evidence.

Upgrade: verify ZIP/manifest, back up Ads files/configuration, exclude overlapping
writers under the existing durable deployment ownership mechanism, then replace
only reviewed payloads. Preserve active client.php/external config, settings and
journals. No reactivation, migration, provider setup, queue run or export is needed.
Keep real delivery disabled. This task packages the correction; it does not install
it or alter client settings. A rollback restores only task-backed Ads files after
compatibility/cessation review, never whole-database state or Consent barriers.
