# Operations — SiteEngine Ads Bridge 0.6.2

## Canonical client lifecycle

`New -> Qualified | Unqualified | Won`

- Qualified: good lead; exports `SE | Qualified Lead` when consent/identity/submission gates pass.
- Unqualified: internal negative outcome only; reason stored in `customer_rating`; no Google conversion row.
- Won: sold lead; exports `SE | Converted Lead` with actual `invoiced_amount`.

With Lifecycle Compliance Automation enabled, Ads Bridge manages lifecycle exclusivity and milestone timestamps. It never invents invoice revenue.

## Standard workbook

Only two worksheets are required:

1. `Google_Ads_Import` — worksheet index 0
2. `All_Leads`

Run dry-run first:

```bash
wp se-ads provision-sheets
```

Then apply only after the reported migration is acceptable:

```bash
wp se-ads provision-sheets --apply
```

`Google_Ads_Import` has 21 columns and contains both Qualified and Converted events. Google Data Manager differentiates them using the final `Conversion action` column.

`All_Leads` has 94 columns and is append-only. It includes intake plus later lifecycle snapshots and the trailing `Inquiry ID` column.

## CRM field provisioning

Dry-run:

```bash
wp se-ads provision-fields
```

Apply:

```bash
wp se-ads provision-fields --apply
```

Required operational fields include `qualified_at` and `unqualified_at`.

## Compliance queue

```bash
wp se-ads compliance
```

Review stale New leads, conflicting/no lifecycle state, missing Unqualified reason, and incomplete Won records.

## Aggregate export

Dry-run:

```bash
wp se-ads export-enabled --dry-run --limit=25
```

Apply:

```bash
wp se-ads export-enabled --apply --limit=25
```

Execution order:

`All_Leads intake -> lifecycle ledger -> qualified_leads -> converted_leads`

Recommended cron cadence: every 10 minutes.

## Google conversion filters

Use the same first-tab Sheet source for both offline actions:

- `SE | Qualified Lead`: filter `Conversion action = SE | Qualified Lead`
- `SE | Converted Lead`: filter `Conversion action = SE | Converted Lead`

Do not use `Conversion value > 0` as the stage discriminator.

## Won requirements

Google Converted export remains gated by:

- Won lifecycle authority
- deterministic canonical Fluent Forms submission
- `invoiced_amount > 0`
- `closed_date` present
- email or phone present
- no DNC poison pill
- authoritative `ad_user_data=granted`

Lifecycle automation can stamp close/qualification dates. Invoice amount remains manual.

## Unqualified

Unqualified produces no Google conversion. The internal lifecycle snapshot records:

- lifecycle event = Unqualified
- unqualified timestamp
- `customer_rating` reason

Historical Lost_* worksheet export is retired.

## Doctor

```bash
wp se-ads doctor
```

The Google Ads timezone check remains an operator-confirmation warning: confirm the Google Ads account timezone matches the configured conversion timezone before live Data Manager imports.

## Inquiry persistence / repeat customers

0.6.0 does not auto-create its inquiry table. Before CRM Ops enables repeat-inquiry behavior:

1. `wp se-ads inquiry-admin migrate`
2. inspect the dry-run
3. `wp se-ads inquiry-admin migrate --apply`
4. `wp se-ads inquiry-admin inspect`
5. adopt every existing CRM Ops current inquiry before allowing that contact to Start New Inquiry
6. keep customer-facing CRM Ops automation held until the prepare -> activation -> committed acknowledgment saga is fully tested

`inquiry-admin adopt` is dry-run by default. For normal production integration, CRM Ops should call the stable PHP adapter `seab_inquiry_adopt_current()` instead of shelling out or writing the Ads Bridge table.

A pre-0.6 current inquiry with a nonblank but unresolvable `submission_id` fails adoption closed. Fix the canonical Fluent Forms binding rather than adopting ambiguous attribution.

Once a contact has Ads inquiry history, its mutable FluentCRM `submission_id` is no longer used as advertising attribution authority.


## 0.6.1 upgrade scope

0.6.1 requires no new schema or worksheet migration over the already migrated 0.6.0 installation. It corrects blank-field deletion and verifies the inquiry reset. Start New Inquiry and customer-facing inquiry automation remain held pending the hardening and live integration checks in `INQUIRY-ACCEPTANCE-STATUS.md`.

## 0.6.2 upgrade and acceptance

No schema or worksheet migration is added over migrated 0.6.0/0.6.1. Inquiry activation requires InnoDB and working connection-scoped GET_LOCK functions. The lifecycle, inquiry and new inquiry-transition isolated suites can run directly with PHP; they do not bootstrap WordPress or write real CRM/Sheets.

The opt-in `tests/inquiry-database-acceptance.php` suite makes actual SQL changes only in a dedicated database named `seab_inquiry_acceptance`, with a random table prefix removed afterward. It refuses any other database and requires SEAB_TEST_DSN, SEAB_TEST_DB_USER, optional SEAB_TEST_DB_PASSWORD, and SEAB_TEST_ALLOW_DB_WRITES=1 plus PDO mysql. Do not point it at a WordPress database. Without opt-in it prints SKIP and does not connect. CRM operations remain simulated in this suite.

Start New Inquiry and customer-facing inquiry sends remain held pending projection recovery and live CRM Ops integration acceptance.
