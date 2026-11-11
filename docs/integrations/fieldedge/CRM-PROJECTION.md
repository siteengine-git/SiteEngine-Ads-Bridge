# Synthetic FieldEdge → FluentCRM projection — November 11, 2026

Unreleased FE-owned milestone in the existing FE worktree/branch. AB Codex owns
isolated installation, field setup, runtime integration and installed verification;
FE Helper retains FE scope. No new global loader, hooks, cron, schema, production
records or provider requests. The previous production investigation stays closed.

## Owned data

| FluentCRM custom field | Native type | Content |
| --- | --- | --- |
| `se_fe_identity` | text | Canonical JSON with provider, tenant_id, customer_id |
| `se_fe_customer_json` | textarea | Customer ID, name, phone, source_updated_at |
| `se_fe_jobs_json` | textarea | JSON map keyed by provider job ID; each value retains job/customer IDs, status, service category, scheduled/completed/source timestamps |

Native email is used only to create the explicitly test-owned synthetic contact.
New contact starts unsubscribed; existing subscription status is never written.
Native name/phone/address, unrelated custom fields, tags/lists, DNC, consent and
sending settings are outside the write allowlist. This is customer intelligence;
there is no advertising-inquiry ID, attribution decision, outcome or export.

The input requires synthetic=true, fieldedge, a synthetic tenant and .invalid email.
The native adapter additionally restricts contacts to fe-projection-*@example.invalid,
admin WP-CLI and an explicit isolated execution constant. There is no HTTP receiver.

Identity resolution checks at most two matches each for exact provider identity,
email, and same-tenant job ownership using supported FluentCRM ORM/custom-field
relations. Ambiguous/conflicting results hold without merging. An unbound existing
email needs an explicit approved_contact_id; matching contact data is not approval
to bind. A bound provider customer can retain its CRM email if a later synthetic
source email changes; native email is not rewritten. Foreign job ownership holds.

One request-scoped MySQL named lock serializes FE projection check/write/readback
within the current WP database/prefix. No CRM table SQL, schema or outcome ledger.
Noncooperating external writers are outside this lock; supported unique-email ORM
insert refuses an insert race rather than upserting the foreign winner. New contact
insert followed by custom fields is not transactional: a partial/uncertain result
holds for inspection, never automatically retries or deletes the contact.

Exact owned-field replay skips all writes. A newer snapshot replaces the same job
key; another job adds a distinct key, capped at20. Older or same-timestamp conflicting
snapshots and corrupt existing data hold. No deletion, backfill, sweep or polling.
Fresh custom-field readback verifies persisted owned values after each write.

## Supported API evidence

Inspected actual isolated FluentCRM3.3.1 Contacts.php, Subscriber.php and
CustomContactField.php supplied privately by AB Codex. Published API references:
[Contacts API](https://developers.fluentcrm.com/global-functions/contact-api-function),
[Subscriber](https://developers.fluentcrm.com/database/models/subscriber),
[CustomContactField](https://developers.fluentcrm.com/database/models/custom-contact-field).
The native adapter uses FluentCrmApi('contacts')->getInstance() ORM queries/create,
getContact(), custom_fields(), and syncCustomFieldValues($owned,false).

The underlying model insert creates a contact without the upsert race recovery of
Contacts::createOrUpdate. Existing contacts receive custom-field writes only.
syncCustomFieldValues emits custom-data-updated hooks on changes. No hooks are
removed; AB must verify its existing isolated automation/queue baseline and holds.
Request-local pre_http_request/pre_wp_mail backstops deny network/email in the demo;
they do not prove queue/enrollment invariance or replace installed verification.

## Isolated setup and runnable command

AB retains its existing executor/allocation and checks actual home/root/owner,
dependency pins, field definitions, automation/enrollment/outbound holds first.
Do not use a client/main site. Copy FE files preserving relative layout; no shared
loader changes are necessary for the explicit CLI script.

Use the supported field manager to merge these definitions into existing definitions;
saveGlobalFields replaces its input, so passing only the three FE definitions would
discard unrelated definitions. Under the existing isolated executor lock:

```php
$manager = new FluentCrm\App\Models\CustomContactField();
$existing = $manager->getGlobalFields()['fields'];
// Append missing CRMProjection::definitions() only. If an existing FE slug has
// a different type, HOLD; do not replace it. Retain all existing definitions.
$manager->saveGlobalFields($merged);
// Read getGlobalFields()['fields'] back independently and verify the exact merge.
```

The native command is deliberately explicit. AB substitutes the verified isolated
home/root and absolute installed FE file path; admin user and allocation remain AB-owned:

```sh
wp --user=<isolated-admin> eval 'define("SITEENGINE_FE_PROJECTION_ISOLATED", true); $args = ["initial", "<verified-isolated-home>", "<verified-isolated-root>"]; require "<FE-root>/tools/fieldedge/projection-wordpress-demo.php";'
```

Actions: definitions (read-only), initial, updated, replay, conflict, read (read-only).
First initial expects created/job_count1. Independently retain the created contact ID
and baseline unrelated fields/status/DNC/tags/lists/queue/enrollment/settings. updated
expects updated/job_count1. replay in a NEW PHP process expects replayed/same ID/count,
with exact persisted values unchanged. conflict expects held/provider_identity_conflict
and no second contact. read produces fresh owned fields and unsubscribed check.
Initial rerun after updated is a stale snapshot and must hold; do not reset fixtures.
Use replay after the update. On uncertain execution, read first; never recreate.

Each invocation changes at most one test contact and three owned fields, adds at
most one job summary, creates at most one unsubscribed contact, makes zero provider
requests and uses no production evidence. Native installed effects remain AB-owned.

## Local verification

Run from the FE worktree with the retained local PHP8.3.36 runtime:

```powershell
& '..\local-test-runtime\php-8.3.36\php.exe' -n .\tests\fieldedge-projection-acceptance.php
& '..\local-test-runtime\php-8.3.36\php.exe' -n .\tools\fieldedge\lead-create-dry-run.php
```

31 focused local assertions PASS;6 new PHP files lint clean. The contact-store
fixtures are separate from actual FluentCRM installed
verification. They cover create/update/replay, identity ambiguity, job ownership,
unrelated/status/DNC preservation, stale/conflicting snapshots and uncertain writes.

## Lead creation: dry run only

lead-create-dry-run.php emits one concrete synthetic JSON payload and SHA256 for
documented [POST /v1/leads](https://docs-dev.fieldedge.com/leads). It contains no
credentials or HTTP executor. source/service values and required tenant semantics
remain unvalidated. marketingEmailOptedIn=false is explicit; do not derive consent.

Retain exact request identity/body and any returned leadId before any separately
authorized future send. Known success/same body skips a new POST; different content
holds. Timeout, disconnect,5xx or duplicate response holds for reconciliation with
no automatic POST retry or regenerated trackingId. The documentation does not
establish trackingId as an idempotency key or a lookup endpoint. GET by leadId is
usable only when that ID is known independently; otherwise provider assistance is
needed to resolve uncertainty. No new durable ledger or transport was built here.
Granted leads:write is not permission to execute a production POST.
