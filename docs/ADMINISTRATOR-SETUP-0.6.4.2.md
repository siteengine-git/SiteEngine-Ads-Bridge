# Reusable administrator setup, 0.6.4.2

Status: locally verified, UNACCEPTED. Installation and native administrator verification
on Alma are blocked by another active shared configuration executor. This document
describes the implemented controls; it does not claim an installed acceptance pass.

## Fresh client installation

1. Install SiteEngine Base and the required Fluent Forms, FluentCRM and SiteEngine
   Consent dependencies, then upload and activate the Ads Bridge ZIP using
   **Plugins > Add New Plugin > Upload Plugin**. PHP 8.3 or newer is required.
2. In **SiteEngine > Business Data**, save **Business Name**, **Business Timezone**,
   **Currency**, and **Google Ads Customer ID**. A valid `America/Chicago` value is
   valid as saved; do not replace it to satisfy a generic setup message.
3. In **SiteEngine > Ads Bridge > Storage**, review the displayed inquiry/guard
   state, check the confirmation, and select **Initialize Required Ads Storage**.
   This invokes the existing explicit inquiry-schema-2 and guard-schema-1 routines.
   It does not adopt existing contacts, reset journals, create consent, or enable
   delivery or exports. An existing partial/unsupported schema is an explicit
   migration-review failure, rather than an automatic repair. Pending work must
   be recovered by its owner. A fresh installation needs no client PHP script.
4. In **Ads Bridge > Setup**, save **Client Slug**, **Short Name**, **Master Fluent
   Form**, and **Consent Policy Version** matching the site's real Consent policy.
5. In **FluentCRM > Contacts > Tags**, create genuinely missing lifecycle tags if
   needed. In **Ads Bridge > CRM & Lifecycle**, select four distinct existing
   tags for **New**, **Qualified**, **Unqualified**, and **Won**. Each exact title
   must identify one tag. Resolve duplicate titles in FluentCRM first. Also map
   Customers, Future Customers and Do Not Contact to the actual lists. Save.
6. Review **Field Mapping** against the actual form/feed, including canonical
   submission ID mapping. Run **Diagnostics** and follow each field's named
   destination. Runtime/vendor/Consent warnings still need their actual owners.
7. Keep **Lifecycle Compliance Automation**, **Google Sheets**, **All Leads**,
   **Qualified Lead**, **Converted Lead**, and retired Lost exports disabled during
   setup. Export/Sheet credentials and scheduling are separate deliberate setup
   work. No setup action configures or schedules them automatically. Google Ads
   API upload remains disabled.

## Existing external or plugin-local configuration

The authority banner identifies the effective resolver source. External
`SEAB_CONFIG_FILE` wins by default, and ordinary settings saves are blocked while
it remains authoritative. Repeated UI saves cannot resolve that override.

In **Ads Bridge > Migration**, review the current authority. For an external file,
check **Import the current effective values and make WordPress settings
authoritative for this external file**, then select **Import Current Configuration**.
This deliberate choice imports the effective configuration, retains existing
disabled flags and unexposed known configuration, and records the reviewed external
path and SHA-256. Neither the external file nor `wp-config.php` is edited. Missing
lifecycle mappings remain empty so the administrator must select them explicitly.
After the redirect, the banner must say **Base-managed WordPress settings**.
Shared facts then come from Business Data; Ads-specific values come from Ads tabs.

If the external file later changes, the saved binding no longer matches. External
authority resumes, settings saves are blocked, and the banner explicitly requires
a new Migration review. Choosing to retain external authority is supported: do
not submit the switch, and continue managing values through that external source.
No override is silently ignored. Plugin-local imports use the existing settings
precedence and do not require an external-authority switch.

## Field destinations and blocked states

| Field or requirement | Administrator destination |
| --- | --- |
| `client.name`, `client.timezone`, `client.currency`, `client.google_ads_customer_id` | SiteEngine > Business Data |
| `client.slug`, `client.short_name`, `fluent_forms.form_id`, `consent.required_policy_version` | Ads Bridge > Setup |
| `fluentcrm.tags.lifecycle.new`, `.qualified`, `.unqualified`, `.won` | Ads Bridge > CRM & Lifecycle; missing/duplicate tag titles in FluentCRM > Contacts > Tags |
| `fluentcrm.lists.*`, lifecycle automation fields | Ads Bridge > CRM & Lifecycle |
| `fluent_forms.fields.*` | Ads Bridge > Field Mapping |
| `exports.*` | Ads Bridge > Exports |
| `google_sheets.*` | Ads Bridge > Google Sheets |
| `storage.inquiries`, `storage.configuration_guard` | Ads Bridge > Storage |
| External authority or stale migration binding | Ads Bridge > Migration |
| Unfinished shared configuration job | Complete/recover the existing owning job; never delete/steal its lock |

Every mutation requires `manage_options` and its WordPress action nonce. Lifecycle
selection also validates exact unique vendor identity. Setup consults the existing
shared executor/durable job when available and uses the Ads destination lock to
reject pending inquiries or configuration journals. No new coordination service is
installed. Output, values and operator error messages are escaped.

## Remaining Alma acceptance

Install the exact reviewed 0.6.4.2 package directly under the existing
ownership controls. Test the administrator handlers with real WordPress nonces and
capabilities; demonstrate missing-storage initialization and missing lifecycle
selection in bounded owned fixtures. Verify idempotency and preserve original
settings, inquiry/Consent records, journals and all disabled delivery/export flags.
Reuse existing evidence. No browser acceptance campaign or client-site development
is part of this milestone. Until these checks pass, 0.6.4.2 is not an accepted release.
