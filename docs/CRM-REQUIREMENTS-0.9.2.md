# CRM requirements 0.9.2

The single Set up CRM requirements action owns the complete Ads and FieldEdge
structure. It preflights all lists, tags and custom fields before the first save.
Compatible definitions are adopted; existing labels, extensions and contact
values are preserved. Duplicate/malformed definitions and type conflicts stop
before initial mutation. An exact repeat creates no definitions and changes no
existing definitions. Original import evidence and native content remain intact.

## Lists and tags

Lists: Customers, Future Customers, DNC (or preserved configured role names).
Lifecycle tags: New, Qualified, Unqualified, Won (preserved configured role names).
Lost is a distinct standard outcome-summary tag. It is not a fifth mutually
exclusive contact lifecycle state. No status is applied to any contact by setup.
Multiple FieldEdge jobs can belong to a contact; a proven inquiry/job association
remains the outcome authority. An unscheduled event or absence of activity never
establishes loss. No speculative loss date/reason fields are provisioned here.

## Required contact fields

| Slug | FluentCRM stored type |
| --- | --- |
| invoiced_amount | number |
| closed_date | date |
| qualified_at | date_time |
| submission_id | text |
| ads_export_status | text |
| ads_exported_at | date_time |
| ads_conversion_value_frozen | number |
| ads_export_error | textarea |
| se_ads_unqualified_reason (compatible explicit legacy reason mapping preserved) | select-one |
| se_fe_identity | text |
| se_fe_customer_json | textarea |
| se_fe_jobs_json | textarea |

The three FieldEdge definitions come directly from CRMProjection::definitions().
FluentCRM's API field keys (including single-select / stored select-one) remain
supported. Setup reports the full combined missing count and readiness.
API Setup / FieldEdge remains read-only diagnostics and links to the main setup
utility. The old protected provisioning endpoint redirects without mutating.

No FluentCRM workflow, campaign, email sequence, sending or enrollment is created
or enabled. Existing Ads lifecycle handling and export settings are preserved;
this action does not start them. FE transport and automatic synchronization remain
disabled. DNC, subscriptions, independent Consent and original attribution are
unchanged. This additive correction addresses GitHub issue #3.

## Customer Rating compatibility

Customer Rating may legitimately contain Excellent, Good, Fair and Poor. Setup
never replaces those choices or migrates their contact values. Fresh settings
use se_ads_unqualified_reason with the six canonical disqualification reasons.
On deliberate main setup, an explicitly configured customer_rating is retained
only when its stored type and choices exactly match the legacy reason contract.
An absent or incompatible legacy field selects the dedicated slug. All twelve
fields and eight terms are preflighted before creation; a collision at the new
slug still refuses before writes. The existing protected setup handler saves the
resolved reason mapping only after provisioning succeeds and confirms readback.
A pending mapping correction remains a setup requirement even when definitions
already exist. No hidden activation migration or background task is introduced.

New inquiry projection clears the configured reason field, not unrelated rating
values. Internal inquiry column customer_rating and the existing All Leads header
retain their historical schema names; this is not a contact-rating import. No
schema rewrite, historical journal translation or contact-value copy is performed.
