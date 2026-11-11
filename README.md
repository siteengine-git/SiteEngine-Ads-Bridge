# SiteEngine Ads Bridge 0.8.2

Ads Bridge connects a selected Fluent Forms lead form, FluentCRM lifecycle and
SiteEngine Consent to immutable advertising inquiries and append-only Google
Sheets feeds. PHP 8.3 and SiteEngine Base are required. Fluent Forms, FluentCRM
and the reviewed Consent contract 2 authority are required for the corresponding
form/advertising features. The separate CRM Configuration plugin is not required
on a destination site.

## Normal administrator setup

Open **SiteEngine > Ads Bridge > Setup**. Incomplete setup is saveable; import,
credentials accessibility and export readiness are not prerequisites to saving.

1. Complete shared **Business Data** in Base. Ads reads business name, timezone,
   currency and account facts there. A different WordPress timezone is not itself
   an error; record the manual conversion-account/timezone verification in Setup.
2. Import a reviewed CRM configuration if needed. Use **Resume saved import** for
   an unfinished operation; retain its identity and completed steps. Import never
   authorizes enrollment or sending. Retained active/uncertain ownership blocks
   conflicting changes and must be resolved using its original recovery workflow.
3. Select the lead form and verify detected field mappings. Preserve custom
   mappings; select ambiguous fields explicitly. Verify the Forms-to-CRM feed
   independently, including `{submission.id}` to `submission_id`, and record its
   operator verification. Field-name detection alone does not prove feed mapping.
4. Review Customers/Future Customers/DNC and New/Qualified/Unqualified/Won mappings.
   Provision genuinely missing required CRM fields through administrator Setup.
   Optional source tags may remain **Unused**. `unqualified_at` is not a required
   CRM field: the inquiry records Unqualified timing internally.
5. Configure Sheets, conversion actions, value/date fields and a service-account
   file outside the web root. Missing path, file and access failures are distinct.
   Saving a path does not prove that the web/CLI execution identity can read it.
6. Review **Overview** and **Advanced > Diagnostics**. Features and all export
   families stay disabled until deliberately enabled for the intended site.

Activation and normal administrator setup prepare absent core inquiry, guard and
ledger storage using existing protected additive routines. Healthy storage needs
no action. Incompatible schemas or unfinished work are preserved with a recovery
action; never drop tables or clear journals to get Ready.

## Configuration authority

Fresh installations use WordPress settings plus Base shared facts. Intentional
external `SEAB_CONFIG_FILE` configuration remains authoritative until an
administrator explicitly chooses **Setup > Configuration authority > Use
WordPress settings**. This preserves saved values and the original file, and
remains effective after file changes/removal. Importing readable legacy settings
is optional and requires a separate explicit choice. An external flag does not
prove the file exists, is readable or contains valid configuration.

## Supported behavior and safety

**Overview**, **Setup**, **Leads** and **Advanced** are the four primary areas.
Leads retains the compliance queue; Advanced contains full overrides, diagnostics,
scheduler instructions, storage inspection/recovery and legacy tools.

Each form inquiry freezes its original form/entry and `ff:{form_id}:{entry_id}`
binding. Signed exact-row historical Consent evidence, frozen negative choices,
required policy and fresh current eligibility/DNC gates apply before browser
advertising payloads and again before each advertising dispatch, including retries.
Withdrawal cannot be undone for older events by a later re-grant. Personalization
is independent. Internal All_Leads reporting is not advertising permission.

Google_Ads_Import remains 21 columns and All_Leads 94 (Inquiry ID last). Qualified
and Converted stages have independent append-only receipts. Unqualified creates
no Google conversion. Actual invoice value is never inferred. Google Ads API
upload remains disabled. Scheduling is explicit: Advanced provides the site/PHP
command; no operating-system cron job is installed by this release. Do not run an
apply command merely to clear a warning. Disabled/never-run/stale/failed are
different runtime states.

## Development interfaces retained, production delivery held

Trusted same-site outcome/prospective APIs retain durable prepare/claim/observe/
read and exact replay/conflict behavior. Their separate optional storage is not a
normal setup requirement. The only outcome value policy is the isolated
`synthetic-usd-demo-1` demonstration, not production revenue. There is no automatic
FieldEdge transport, production lead/job continuity, browser endpoint or scheduler
for these interfaces. Development launchers, credentials and private pilot cards
are excluded from the installable package.

Call identity stays explicit (`twilio:CA...`), with no invented form entry.
Call advertising remains held: positive original-call Consent and the required
authoritative call identity/start evidence are not established. Duration alone is
not business qualification or advertising permission. Call Bridge remains owned
and released independently; Ads does not install it or send calls.

## Installation, evidence and limits

Back up this plugin and preserve settings, inquiry/Consent history, journals and
active ownership. Serialize replacement, verify the complete package manifest and
use the site's supported PHP. Upgrades retain custom mappings and disabled flags;
never destructively uninstall or restore the whole database for file rollback.
See [0.8.2 installation and limitations](docs/RELEASE-0.8.2.md).

Accepted isolated native/Consent/broker/replay and the one real synthetic worksheet
append/readback/replay are carried evidence, not repeated live-delivery claims.
This release's focused main-Alma verification is recorded separately under
`releases/0.8.2`. No client rollout, real customer Google delivery, FieldEdge POST,
positive call Consent or provider lead-to-job continuity is claimed.
