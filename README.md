# SiteEngine Ads Bridge 0.9.1

Ads Bridge connects a selected Fluent Forms lead form, FluentCRM lifecycle and
SiteEngine Consent to retained advertising inquiries and append-only Google
Sheets feeds. PHP 8.3 and SiteEngine Base are required. Fluent Forms, FluentCRM
and Consent supply their corresponding form and advertising capabilities.
The CRM Configuration builder plugin is not required on destination sites.

The main CRM setup includes all three FieldEdge fields and a distinct Lost
outcome-summary tag. FieldEdge diagnostics no longer require a second field
provisioning action. See [the complete field contract](docs/CRM-REQUIREMENTS-0.9.1.md).

## Setup

Open SiteEngine > Ads Bridge > Setup. One page and one Save settings button
retain ordinary settings, including incomplete configuration. Shared Business
Data comes from Base. Consent supplies policy identity; no grant is invented.

Set up CRM requirements creates only missing mapped lists, lifecycle tags and
standard fields. Existing definitions, contact values, content and edits remain
intact. No ZIP, email template, campaign, journey or sequence is required.
Select the lead form and enable Ads lifecycle handling. Its FluentCRM feed is
inspected for the exact submission ID mapping; inspection proves configuration,
not actual contact delivery. Remaining requirements identify actual blockers.

Test connection uses draft spreadsheet ID and private credential-file path
without saving. It authenticates and reads spreadsheet identity only; no rows
are read or appended. Read access does not prove write permission.
Google Ads account timezone is distinct from Base business timezone.

API Setup contains read-only FieldEdge diagnostics. The main CRM setup owns all
required fields. FieldEdge transport and synchronization remain disabled.
Settings retains advanced field overrides, detailed diagnostics, scheduler
instructions and lead compliance. Printed cron instructions do not prove an OS
schedule exists; recorded runs supply runtime and freshness evidence.

Historical import registry, jobs, receipts, existing content and Base/service
smartcodes remain intact. Only genuine unfinished-operation recovery remains
in Diagnostics; normal CRM ZIP import is removed.

Saving and provisioning do not run advertising exports, send mail or enroll
contacts. Actual advertising eligibility, Consent, DNC, original evidence,
append uncertainty and retry protections remain in force. Call advertising and
production FieldEdge lead creation are not accepted by this setup release.

See [setup and upgrade details](docs/SETUP-0.9.0.md) and releases/0.9.0 for scoped
installation and actual workflow evidence. Client production rollout and live
conversion delivery are separate from this development-site setup verification.
