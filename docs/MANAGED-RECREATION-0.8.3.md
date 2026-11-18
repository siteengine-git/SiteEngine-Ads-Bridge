# Managed-definition recreation — 0.8.3

Open Ads Bridge > Setup > Import CRM configuration. A new validated upload may
review native definitions deliberately deleted since the earlier completed import.
The review labels recreation with its previous ID; it is not a registry reset.
Inspect the mapping and actions, choose Keep for destination edits to preserve,
then use Import configuration. The ordinary workflow advances bounded batches.

Missing rows must be established against valid managed identities. Multiple or
alternate matches still require resolution; invalid IDs are not evidence of a
missing object. Created definitions receive new IDs. Surviving definitions whose
supported relationships point to recreated parents/tags are explicitly reviewed
as relink operations, preserving the chosen portable body and other edits.

The plan freezes old reference identities for exact pre-write comparison and uses
new identities for post-write/final readback. Deletion, edits, ID reappearance and
new collisions after review still stop. A pending create without a returned ID is
never replayed. Pending relink/update may complete only after exact before/current
readback. Original completed import jobs/receipts remain intact; the coordination
journal retains retired operation bindings using the existing contract.

Upgrade only with no active/uncertain import. An uploaded zero-write job can be
reviewed again with the new importer while preserving its saved ZIP and mappings.
An existing running operation requires its original supported recovery; do not
upgrade or rebind its source blindly. No schema change, journal purge or background
repair is introduced. Back up plugin files and native configuration before use.
Rollback of plugin files is not permission to delete recreated definitions or reset
journals. Once an import has begun, preserve its exact source/operation and complete
or recover it before a downgrade.

All imported automation/email/step definitions remain draft. Lifecycle automation,
Sheets and export families remain disabled. This correction does not enroll or send,
restore deleted contacts/history, or fabricate attribution/Consent.
