# FieldEdge Integration — External Technical Review

**Prepared:** November 13, 2026 | **Review meeting target:** Monday, November 16, 2026
**Organization:** SiteEngine Web Services LLC | **Pilot:** Climatrol Air
**Project state:** Real API -> CRM synchronization verified on Climatrol DEV; broader end-to-end product not yet complete.

## Start here

**Review this branch, not the default branch:**

[`review/fieldedge-20261116`](https://github.com/siteengine-git/SiteEngine-Ads-Bridge/tree/review/fieldedge-20261116)

The default `main` branch retains an earlier Ads Bridge baseline and does **not** represent the installed Climatrol or FieldEdge connector version. The FieldEdge implementation is maintained on a separate development branch to prevent unrelated Ads Bridge changes. Installed Climatrol DEV dependencies include Ads Bridge **0.9.1**; the stand-alone FieldEdge connector is **0.1.1**.

## What we verified on real systems

- Existing, securely stored OAuth credentials authenticated with FieldEdge (HTTP 200).
- Bounded read-only FieldEdge requests returned customer, work-order and invoice data. A separate verified pass retrieved one customer's three individual work orders with four business GETs, all HTTP 200.
- Original Fluent Forms form 39, entry 889, was linked to an existing FluentCRM contact using exact email, timestamp and a successful native Fluent Forms integration log. The submission stored advertising click identifiers.
- The same CRM contact matched a FieldEdge customer with three work orders. All three fetched work orders reported completed and contained explicit invoice references. One invoice was also fetched by its explicit identifier in an earlier bounded live read.
- The stand-alone connector **0.1.1** was installed and activated on the Climatrol development WordPress site. Three genuine work-order summaries were stored in the three dedicated FieldEdge CRM custom fields and read back successfully; identical repeat input returned `replayed`.
- Original CRM email/subscription status stayed unchanged, no historical Ads Bridge inquiry was fabricated, and wrong-hash input was refused.
- Site health HTTP 200. The WordPress connector is silent on ordinary web requests and exposes an authenticated owner-scoped WP-CLI interface.

**Not yet verified:** Which specific job was caused by the original website inquiry; FieldEdge `leadId` continuity to work-order ID; automatic lifecycle transitions or Google Ads outcome export. Imported historical Won/Lost CRM tags are not accepted as business facts.

## Where to inspect

| Path | Purpose |
| --- | --- |
| [`standalone/siteengine-fieldedge-connector/`](standalone/siteengine-fieldedge-connector/) | Installable WordPress connector source (0.1.1) |
| [`releases/0.1.1/`](releases/0.1.1/) | Versioned ZIP, SHA-256 and DEV release card |
| [`includes/connectors/fieldedge/LiveCRMProjection.php`](includes/connectors/fieldedge/LiveCRMProjection.php) | Pure verified-read normalization and replay/conflict guards |
| [`includes/connectors/fieldedge/LiveCRMProjectionWordPress.php`](includes/connectors/fieldedge/LiveCRMProjectionWordPress.php) | Owner-scoped FluentCRM field-only writer |
| [`includes/connectors/fieldedge/LiveSnapshotDiscovery.php`](includes/connectors/fieldedge/LiveSnapshotDiscovery.php) | Bounded customer/work-order reader |
| [`includes/connectors/fieldedge/OAuthClient.php`](includes/connectors/fieldedge/OAuthClient.php) | Secret-safe OAuth and strict GET transport |
| [`tools/fieldedge/Invoke-Climatrol-Dev-Sync.ps1`](tools/fieldedge/Invoke-Climatrol-Dev-Sync.ps1) | Local Windows pilot poller; **not** multi-tenant automation |
| [`releases/0.1.1/RELEASE-CARD.md`](releases/0.1.1/RELEASE-CARD.md) | Exact packaged/installed/tested claims and exclusions |
| [`docs/integrations/fieldedge/HISTORICAL-CONTACT-LIVE-READ-20261113.md`](docs/integrations/fieldedge/HISTORICAL-CONTACT-LIVE-READ-20261113.md) | Sanitized live API and original form evidence |
| [`docs/integrations/fieldedge/CLIMATROL-DEV-AUTO-SYNC-20261113.md`](docs/integrations/fieldedge/CLIMATROL-DEV-AUTO-SYNC-20261113.md) | Scheduler implementation and remaining reliability issue |

## Reproduce offline acceptance

Requires PHP 8.3+ CLI. The following tests use synthetic fixtures, make **zero provider requests**, and do **not** touch WordPress or customer data. Run from this branch's repository root:

```sh
php -n tests/fieldedge-live-projection-acceptance.php
php -n tests/fieldedge-live-snapshot-acceptance.php
php -n tests/fieldedge-historical-contact-acceptance.php
php -n tests/fieldedge-targeted-acceptance.php
php -n tests/fieldedge-oauth-acceptance.php
```

Last verified locally: **29 + 12 + 12 + 45 + 43 = 141** focused assertions passing. Native installed acceptance and the four successful provider GETs are documented separately; the tests alone do not prove external connectivity.

## Technical boundaries and safety

- FieldEdge OAuth secrets and real provider responses are stored outside Git using Windows current-user DPAPI and private local journals. No real customer personal data, OAuth token or raw invoice total is intentionally committed as acceptance material.
- No production `POST /v1/leads`, webhook registration, SMS/email notification, marketing opt-in modification, Google Ads upload, or customer-facing activity was made during acceptance.
- The development contact match verifies the same customer, not the causal relationship between the website inquiry and any one of multiple jobs.
- The Windows Task Scheduler pilot is registered for six-hour runs but has not yet completed independent unattended acceptance. Intermittent pre-HTTP OAuth transport failure remains under investigation. A scheduled task that depends on an interactive Windows session is not a 24/7 production service.
- No FieldEdge partnership, Marketplace publication, product certification or approved distribution claim is made.
- **Public repository:** this GitHub repository is publicly readable. Do not add credentials, live customer payloads, private support tickets or vendor-only response details to issues or commits. Security review: [`EXTERNAL-SHARING-AUDIT-20261116.md`](docs/integrations/fieldedge/EXTERNAL-SHARING-AUDIT-20261116.md).

## Review materials for Monday

- [Climatrol owner briefing](docs/integrations/fieldedge/BRIEF-CLIMATROL-OWNER-20261116.md)
- [FieldEdge integration team briefing and technical requests](docs/integrations/fieldedge/BRIEF-FIELDEDGE-20261116.md)

## Decisions requested

**FieldEdge:** authoritative API lead-to-work-order linkage, `trackingId` continuity, sandbox/partner onboarding path, event notification permissions, and recommended recovery/throttling for intermittently failed OAuth connections. See the FieldEdge briefing for precise questions.

**Climatrol:** one owner-confirmed specific form/call inquiry-to-work-order pair (if available), choice of Won/Lost definitions and invoice-value measurement basis, plus approval for any separately planned customer-impacting pilot. None of these require exposing customer details in a public repository.
