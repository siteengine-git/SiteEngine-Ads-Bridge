# Administrator workflow in0.8.0

Overview summarizes the next action, disabled/enabled exports and actual last successful
runtime. Setup follows Business Data → CRM import → lead form → Sheets/conversion settings
→ readiness. Completed steps collapse and remain editable. Import supplies CRM mappings;
edit them only when needed. Saving incomplete setup is supported; no feature is enabled
implicitly. Lead compliance remains the existing work queue.

Normal activation and the Ads administrator page prepare absent plugin-owned storage under
original executor and coordination protection. Supported schema2 inquiry / absent guard1
and absent canonical ledger additions are handled internally. Older incompatible/partial
inquiry schemas still require their precise supported migration review; they are not
silently repaired. Front-end traffic and queue workers do not initialize storage.

Advanced holds full overrides, diagnostics, scheduler, storage recovery and legacy tools.
Legacy URLs and administrator actions still route to their corresponding area. Legacy
import is shown only for an active actual legacy configuration. No authority changes
occur implicitly. A matching field name never proves a Fluent Forms→CRM feed; its existing
manual verification and optional exact runtime linkage readback remain distinct.

Current reader validates growing producer records up to16MiB at JSON depth512 and projects
bounded ownership scalars; it never decodes history arrays. Three-second inspection limit;
explicit recognized identity/completion/inflight:null/checkpoint under executor ownership.
No completion inference from size, age or a free lock. Credentials-path persistence is
separate from actual PHP filesystem access to the service-account file.

# Import CRM Configuration — Ads Bridge 0.8.0

The CRM builder remains on the authoring site. Destination sites need Ads Bridge,
SiteEngine Base, FluentCRM and Fluent Forms. This package's native Pro sequences
also require FluentCRM Pro 3.3.1. The separate SiteEngine CRM Configuration plugin,
source profiles, CLI tools, JSON editing and manually installed MU files are not
destination prerequisites. PHP 8.3+, mysqli and the ZIP extension are required.
The supported native adapter matrix is FluentCRM 3.2.6 / 3.3.1; Pro sequence
imports require Pro 3.3.1. Other versions stop with the missing capability/version.

1. On the authoring site, save changes in the native FluentCRM editors, then use
   CRM Configuration > Create current configuration ZIP. Export reads actual saved
   supported objects; arbitrary unregistered native objects are not a backup.
2. On the destination, open SiteEngine > Ads Bridge > Import CRM Configuration.
   Upload the ZIP. Choose the actual destination form and its Service input,
   compatible CRM Service/Outcome fields (or the displayed missing definitions),
   and the destination service label. Base facts are read from this destination;
   they are not imported from the authoring site.
3. Review changes. Compatible existing lists/tags/fields are adopted. Managed
   unchanged content is reused. Actual destination edits require Keep or Replace;
   inspect the linked native object and changed fields before choosing. Keep
   persists that choice across identical repeats. A later producer revision cannot
   silently overwrite a kept edit. Ambiguous identities, incompatible shared field
   types/options, active objects and unsupported relationships stop the import.
4. Click Import configuration once. The product explicitly initializes absent Ads
   inquiry schema 2 and configuration guard schema 1 using its existing additive
   migrations. Existing pending operations, partial/incompatible storage or foreign
   writers stop preparation; no schema repair, purge or takeover is attempted.
   Routine batches then advance automatically. The reviewed changes include missing
   standard Ads CRM definitions from the existing provisioner; unqualified_at is
   excluded. No contacts or customer field values are changed.
5. Completion links to native editable templates, campaigns, sequences, automations
   and dependency settings. Imported definitions remain draft. The preview trigger
   is an inert editor representation: entry events, timing, conditions, parents and
   stop-rule descriptions are retained; no enrollment runtime is installed. Views
   are retained as permission-to-send:false configuration, not active audiences.
6. Ads lifecycle/list mappings resolve from logical package keys to actual destination
   objects. All four optional source mappings may be Unused. Incomplete lifecycle
   setup can be saved while automation is disabled; enabling it still requires valid
   distinct lifecycle identities. Other setup errors remain visible in Setup/Doctor.

Import never enables lifecycle automation, Sheets or any export family. It does not
enroll, schedule, send email, append a Sheet or grant advertising/email permission.
Consent/current eligibility and current DNC gates remain independent and unchanged.
Complete real Base business data, Consent policy, actual form/feed mapping, account,
credentials and any separately authorized delivery prerequisites through their own
normal setup. An import completion result is not a Ready/delivery assertion.

## Editable content and placeholders

The consumer accepts ZIP/1 + native-export/2, preserves supported editable bodies,
settings and relationships, and remaps references through destination IDs/slugs.
Native subscription/unsubscribe tokens remain FluentCRM-owned. Ads supplies the
missing Base and secrm smartcode groups only when another owner has not registered
them. Supported Base values are escaped; missing values use the supplied default.
The selected service label is a destination configuration/preview default. It is not
per-inquiry service evidence or a permission to send. A multi-service operational
sender must resolve the actual inquiry under its separately accepted contract.
Unknown double-brace consumers stop upload with the exact token/object; do not
silently substitute source facts. No Consent advertising decision authorizes email.

## Operation protection and recovery

ZIPs have exactly two members, bounded compressed/uncompressed bytes and a verified
manifest/configuration revision. No extraction is performed. Permission and nonce
checks protect every action. Validated upload contents, original ZIP hash, mappings,
review, exact pending intent, native IDs and receipts stay in non-autoloaded
destination-only seab_crm_import_* options. Completed journals are retained. They
are not included in producer exports or public release evidence.

The importer uses the existing shared executor where present and Ads coordination
contract 1, the primary autocommit wpdb session, and verified ORM routing. A finite
group manifest stays reserved between browser requests; each group has at most eight
operations, with a ten-second dispatch deadline checked before operations. PHP
requests have a 20-second execution limit and the browser a 25-second request bound;
these are not a claimed OS hard-kill guarantee for blocked PHP I/O. SQL coordination
statements retain their existing two-second bounds. UI auto-advance is capped at 64
requests. Requests stop on failure; they never skip a failed item. Current importer
source pins bind active work, not unrelated historical completed jobs.

HTTP/mail/scheduler filters are request-local to import. They do not claim to fence
direct SDK calls, other requests, privileged writers or shared services. Only inactive
configuration APIs are called. Other cooperating configuration/inquiry work is held
by the existing durable coordination reservation. Arbitrary external writers remain
outside that lock; changed native values cause readback/conflict failure.

On a correctable error, retain the upload/mappings/progress and use Resume saved
import after inspecting the exact error. Completed native steps are freshly read,
not saved again. An interrupted update may resume only from its exact unchanged
before state or verified intended result. A create whose returned ID was not durably
recorded is uncertain and cannot be automatically recreated or adopted by a latest/
title search. Preserve that journal for exact operation-specific investigation.
Storage and Import show separate physical-schema, live-lock and retained-operation
states. A free lock with an unfinished durable job is a retained reservation, not
permission to replace it. Initialization is unavailable for unfinished operations.
Matching Ads journals link to Resume saved import; completed steps are read back
under the original guard operation/plan. This revision explicitly supports the exact
reviewed c1e8e6d importer source pin without rebinding its original journal. Unknown
source/plan changes still require the pinned original importer and exact recovery.
A create without a durable returned ID is never automatically replayed.

Ordinary settings ownership inspection first holds the original executor lock and
reads bounded deployment-job.json. Recognized complete state, explicit inflight:null
and a durable checkpoint establish completed deployment authority; unrelated historical
admin-state.json is not read. Active/inconsistent/unknown deployment evidence remains
held. Read failures identify the exact record and condition without its contents:
parent access, unreadable/linked/oversized file, malformed JSON or missing completion
proof. Limits are unchanged and a missing original lock is never recreated over
retained records. Credentials-path diagnostics remain a separate file-access check.

Predecessor CRM jobs remain owned by their original recovery workflow. Ads reads
bounded metadata from the existing canonical private directory, including when the
CRM plugin is absent, and does not copy, delete or complete its journals. With the
original plugin available, use the linked saved CRM session; otherwise obtain its
pinned recovery tooling, original job/request/profile, checkpoints and returned IDs
through the CRM owner. The link is inspection, not permission to start a new import.
Missing/ambiguous records stay held. No manual database clearing is supported.
Do not edit pins, delete journals, steal locks or manufacture completion.

Rollback restores only backed-up Ads files when no active import needs the successor.
It does not undo native edits or drop Ads storage. Preserve imported native content,
settings, mappings/journals, inquiry history and all Consent withdrawal barriers.
Do not uninstall/purge a database or restore the whole database. A retained active
operation must be resolved through exact supported recovery before ordinary setup.

## Producer provenance and acceptance

Configuration-only primitives were adapted from CRM checkpoint 7df6810 (0.3.14),
with namespaces isolated under SiteEngine\AdsBridge\Import. PROVENANCE.json records
the exact source-file hashes. The current 0.3.15 producer at c216f9d documents the
unchanged contract in contracts/ALMA-EXPORT-V2.md. Its new normal export/download
was byte-identical to the accepted reference. No CRM operational engine, intake,
sender, source profile, deployment launcher or source journal is packaged in Ads.

Actual browser/native results and exact final package pins are recorded separately
in releases/0.7.0. Local doubles never establish installed SQL acceptance.
