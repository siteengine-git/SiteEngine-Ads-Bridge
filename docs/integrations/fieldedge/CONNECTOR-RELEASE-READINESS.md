# FieldEdge installable connector diagnostics — FE handoff

Status: FE-owned source implemented and locally verified, **not yet packaged,
installed, or released** as an Ads Bridge candidate. AB Codex owns the shared
WordPress loader, setup UI, package, and isolated Alma installation.

## Actual supported product boundary

Existing FE code already provides:

- Synthetic-only FieldEdge customer/job projection through supported
  FluentCRM contact and custom-field APIs.
- Exact identity, replay, conflict and uncertain-result protection for
  prospective lead intents using AB-owned durable callables.
- A verified Windows-to-canonical-Alma *synthetic* lead transport route.
- Read-only OAuth/provisioned provider access and bounded targeted discovery.

This FE supplement adds the independently loadable
`includes/connectors/fieldedge/ConnectorDiagnostics.php` and the explicit
administrator-only readback
`tools/fieldedge/connector-status-wordpress.php`.

Diagnostics make **no provider requests, credential reads, CRM writes, advertising
eligibility decisions or mail sends**. The report includes only fixed state
labels, the three FE-owned field slugs and a conflict/missing-field classification.
It does not reveal stored FluentCRM fields, people, secrets, provider records,
server paths or credential references. In particular, it does not infer whether
a Windows DPAPI credential exists from an Alma PHP request.

The initial production feature MUST remain disabled: no native
`POST /v1/leads`, customer/job sync scheduler, webhook registration,
call-origin Google delivery, client exports, or mail automation.

## Minimal AB integration contract

Only AB Codex may modify the canonical AB runtime or setup UI.

1. Load the FE `ConnectorDiagnostics.php` source through AB's existing
   authenticated admin path. Do not register a public REST or anonymous route.
2. For an administrator with `manage_options`, call
   `ConnectorDiagnostics::wordpress()`. Catch failures and show one safe
   "diagnostics unavailable" message; do not expose exception traces.
3. Use only the returned fixed keys/statuses. Absence of the prospective
   AB callable contract or field definitions is a **hold**; diagnostics never
   auto-migrate or provision fields.
4. When preparing the isolated projection, merge the output of
   `CRMProjection::definitions()` with the **existing** FluentCRM definitions
   through the supported `CustomContactField` manager. Reject type
   conflicts, verify exact readback and preserve unrelated definitions.
   Never pass only FE's three definitions to `saveGlobalFields()`.
5. Keep customer/job projection as an explicit isolated WP-CLI operation,
   using the existing `projection-wordpress-demo.php` and its required
   known home/root/admin identity. Do not infer production support from
   the synthetic harness or bypass its gating.
6. Reuse AB's retained isolated owner/checkpoints and installed evidence.
   Do not recreate a consumed synthetic fixture or reset a claimed intent.

Read-only CLI check via the approved site runner (paths and user must be
substituted with verified **isolated** values):

```sh
wp --user=<isolated-admin> eval '$args=["<verified-home>","<verified-root>"]; require "<FE-plugin-root>/tools/fieldedge/connector-status-wordpress.php";'
```

The CLI runner refuses non-WP-CLI, nonadministrator or mismatched
home/root execution before reading the field registry.

## Status interpretation

- `crm_field_schema=ready`: all three FE field slugs match their supported
  FluentCRM types. This **does not** mean production sync is enabled.
- `missing_fields`, `type_conflict`, `invalid_registry`,
  `not_verified`: require AB/CRM-owned remediation or inspection.
- `crm_projection=synthetic_isolated_cli_only`: the tested projection has
  its current required CRM fields; it does not create real customer data.
- `provider_connection=not_tested_by_diagnostics`: no inference about
  provider OAuth or permission validity. The retained prior OAuth milestone
  remains separate evidence.
- `lead_creation=production_post_disabled`: no native production lead
  submission transport is admitted.
- `provider_lead_job_link=unverified_provider_contract`: no guessed
  Lead ID-to-Work Order ID relationship. Provider clarification is pending.
- `consent_and_dnc=not_changed` and `advertising_attribution=not_assessed`:
  AB retains independent advertising eligibility decisions.

## Verification and rollback

Focused local command:

```powershell
& '..\local-test-runtime\php-8.3.36\php.exe' -n .\tests\fieldedge-connector-status-acceptance.php
```

The new test covers missing/conflicting/malformed field registry, independent
AB callable availability, no customer-value leakage, disabled production
operations and administrator isolation. It uses only synthetic in-memory facts;
**it is not installed WordPress acceptance**.

For AB's affected isolated Alma deployment, verify one normal admin status
read, field-definition exact readback, no HTTP or SQL writes on the status
request, and preserved Consent/DNC/CRM subscription/export controls. Preserve
backup and exact package/manifest pins. Rollback restores only owned installed
files or configuration while retaining prior business records and evidence.

Outstanding **release** work: AB adds UI/loader, packages candidate ZIP and
verifies isolated installation. Outstanding **live pilot** work: approved
operator subject/business values and FieldEdge's supported lead-to-job
relationship/dispatch semantics; do not confuse these with the installable
disabled-by-default candidate.
