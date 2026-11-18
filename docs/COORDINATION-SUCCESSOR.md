# Ads Bridge 0.6.4 — installation and recovery

Canonical source remains the existing Ads checkout. Integrates retained4c448ec into
current Consent-repaired1eec02e. No historical source checkout replaced the current
runtime. Consent helper/front-end/advertising dispatch algorithms are unchanged;
CLI adds only the new administrative command. Inquiry changes are precisely the
retained destination-lock/contact-lock ordering and uncertain-session disposal.
Contract1 capability, pending inspection, with_batch, step, finish, schema_sql,
64 steps/64KiB journal/128 retired operations and interrupted-recovery rules remain.
Inquiry adapter1/schema2 and configuration tree2 stay separate from guard schema1.

## Dependencies / owner boundaries

PHP8.3+ and stock exact-class wpdb/mysqli for installed coordination; supported
MariaDB10.11 authoritative writable primary, autocommit1/no caller transaction,
innodb_flush_log_at_trx_commit1 and SET STATEMENT max_statement_time2. No reconnect/
replay under protection; no multi-primary/failover or physical power-loss guarantee.
No tuning, runtime/access changes or privilege expansion is authorized by this ZIP.
Base remains shared-fact owner. Consent0.6.1-rc.2 implementation1c0a42000927db720cfc60934ea5e2b31db28533,
contract docs b8ceb8c292ff3a198bd34548ec2e5a2d13afe98f are unchanged dependencies.
Unavailable/invalid authority still blocks browser advertising and actual appends;
negative evidence/policy/current DNC/deterministic binding and personalization gates
remain independent. Internal All_Leads94 and Google_Ads_Import21 contracts unchanged.
Google Ads API, Sheets, all exports and lifecycle automation remain disabled/held.

CRM PROTECTED-INSTALLER.md0.3.2 was read with the actual coordinated_phase and
local_protected_session implementation. It verifies capability strings/schema1/2,
max64, same pinned wpdb/ORM session and durable CRM checkpoint INSIDE batch.finish.
It also hard-pins coordination implementation69bc74d9cdf81384415083f2445ffe20ea8c4ab39b4e6a6426e760f0a5e7576d.
Migration adds code to that class, so CRM MUST explicitly review/rebind the new
implementation hash, successor/main/package pins and destination profile. Its owner
owns that adapter and any checkpoint provisioning; no CRM source was edited here.
Ads installation alone is not CRM installation or vendor-session acceptance.

## Ordered Alma slot — commands are preparation, NOT executed here

Ads Codex executes authorized Ads installation/migration/verification directly under
_(AB) Ads Helper - 10 using existing access. Coordination prevents overlapping
writes; it is not another approval gate. Use the common executor lock and Ads
destination protection for changes; preserve foreign controls and journals. The
earlier accepted lead-A exercise is closed and must not be replayed.

1. Preserve restricted Ads/Consent evidence, current Ads plugin files, settings/
   override authority and database/schema/journal state. Do not copy an active
   client.php or credentials into this ZIP. Preserve that existing active config
   on deployment, its path/ownership/permissions and all unrelated plugin files.
2. Confirm supported PHP/DB/session/engine profile and recorded nonoverlap. Exclude
   Ads settings/delete/import/provision-fields/DDL/ledger maintenance, Base/overrides,
   vendor editors/importers/feeds/workers/direct SQL/other connections for the full
   protected AND recovery window. These are not falsely claimed as cooperating
   writers. Preserve CRM contact holds and environment quarantine independently.
3. Verify successor ZIP/external manifest hashes before use, stage non-overwriting,
   lint every packaged PHP with Alma's established PHP8.3 executable, and verify
   member hashes. Replace only this plugin's payload under scoped quiescence.
   No reactivation, forced cron, automatic inquiry migration or site-setting save.
   Missing guard causes inquiry writes/sync to fail closed until step5 finishes.
4. Inquiry schema2/InnoDB/direct seab_inquiry_schema_version2 must already verify
   physically. J0 completed it; do NOT repeat setup merely because this is a new
   package. If absent/partial/unexpected, stop and retain state for Alma review.
5. Via existing owner/profile/shared runner and retained pre-bootstrap guard, read
   WP-CLI synopsis, then preview and explicitly apply only the additive guard:

   ```text
   help se-ads configuration-admin
   se-ads configuration-admin migrate
   se-ads configuration-admin migrate --apply
   se-ads configuration-admin inspect
   ```

   Command arrays for Invoke-Site-Wp.ps1 -WpArguments are exactly:
   @('help','se-ads','configuration-admin')
   @('se-ads','configuration-admin','migrate')
   @('se-ads','configuration-admin','migrate','--apply')
   @('se-ads','configuration-admin','inspect')
   Use ONLY the established profile/guarded executor; no new SSH/key/route. CLI
   apply invokes seab_configuration_migrate(true); preview is default, no --dry-run.
   The library acquires/releases destination protection around DDL/readback and
   validates pending inquiry state without altering it. If a row is already
   reserved/inflight, STOP: it is recovery evidence, never a reset opportunity.
6. Independently read SHOW CREATE TABLE / SHOW COLUMNS / SHOW INDEX and engine for
   the actual prefix+se_ads_configuration_guard. Expected EXACT required fields:
   slot tinyint unsigned NOT NULL PRIMARY KEY; schema_version varchar(8) NOT NULL;
   destination char(64) NOT NULL; state varchar(16) NOT NULL; journal longtext NOT NULL.
   ENGINE=InnoDB; one unique PRIMARY column slot. New table has zero rows; no seed
   row or new version option. Existing valid rows retain exact bytes. Journal rows
   use schema_version1 and exact capability destination. Do not print row bodies.
7. Fresh-process installed payload/profile/pending readback (not an installation
   authorization) via guarded owner runner:

   ```text
   eval-file <installed-plugin>/tools/successor-readiness.php <INDEPENDENT_EMBEDDED_MANIFEST_SHA256>
   ```

   Bind the independent SHA from the delivered manifest, never trust its own field.
   This validates every installed member and actual PHP/wpdb/profile, then calls
   protected physical/session inspection. Complete=true,state=none,write_permission=false
   is required; a version option or command exit alone is insufficient. Existing
   prepared/activating inquiries or pending configuration remain blockers for CRM.
8. Carry completed Alma two-connection/session proof and native interrupted-journal
   recovery. CRM vendor/checkpoint and further browser acceptance retain their own
   scope. Local doubles are NOT native results. The retained opt-in native
   sources target ONLY a separately approved dedicated loopback test DB, not WPDB;
   no database provisioning/Windows initialization was attempted here. CRM must prove
   all actual vendor/core/ORM/cache/hooks share the protected installed session and
   bind this successor before any configuration installer phase is enabled. Rebind
   old candidate-specific acceptance manifest pins before using them for successor
   claims; r3/r4/r5 artifacts stay historical exact bytes. Repeat affected actual
   Consent/Ads checks under the recorded owner assignment after installation.

## Interrupted upgrade / rollback restrictions

No migration hook runs on activation/traffic; copy failure restores ONLY this task's
backed-up plugin files under quiescence after Alma establishes writer cessation.
If CREATE was attempted but reply/proof failed, creation_state=unknown is not absence:
retain the table, logs, controls and journal. Fresh physical readback can establish
an empty valid table, after which an explicitly authorized fresh invocation verifies
it without another CREATE. No automatic retry, DROP, ALTER repair, truncate or seed.
An existing malformed/pending guard blocks and is preserved for owner recovery.

Do not roll back to a nonparticipating build while any configuration operation is
reserved/inflight, CRM installer session/checkpoint is active/uncertain, or an old
writer may survive. Confirm independent writer cessation, retain exact request,
operation IDs, receipts, checkpoints, audit and holds; recover with identical intent
under contract1. Unknown create results cannot be resubmitted or adopted by title.
Never delete the guard row or rotate IDs to clear a pending gate. No whole-DB restore,
uninstall, destructive purge, barrier lowering, global cron restore or hold release.
A file rollback does not undo DDL/data; compatibility rollback requires Alma/CRM
review and continued exclusion of noncooperating writers. Retain new tables/data.

## Evidence / designation

successor/RESULTS.json records fresh local checks, all explicit SQL/WordPress/vendor
boundaries doubled. Stable designation follows actual installed verification, not
ZIP production, historical Golden checks or CRM's separate local90-assertion report.
The rc.1 local implementation itself performed no site writes. Subsequent authorized
Alma migration/setup and bounded native recovery are recorded in release results.
No export, customer send, accepted lead-A replay or other-plugin source edit occurred.

## Stable release evidence and upgrade delta

0.6.4 retains the rc.1 migration and all Consent enforcement. It corrects one
recovery-order condition: earlier completed receipts can be verified while a later
step is inflight; no duplicate writes or clearing of uncertain intent is permitted.
Fresh local regression covers receipt drift, unknown outcomes and ordered replay.
Actual Alma recovery used the identical durable request in a fresh process, with
zero repeated effects. Three marked test tables and both journals remain retained.

Existing Alma guard/inquiry migration is complete: do not repeat it for this
release. Fresh Doctor has zero FAIL/PENDING, module Ready with nine explicit warnings.
Two existing lists were adopted and only four genuinely missing Ads fields were
created, preserving all existing definitions. No warning was suppressed. Live
timezone/feed-mapping/provider readiness remains an operator prerequisite before
separately authorized delivery. All six lifecycle/Sheets/export flags stay false.

Verify release ZIP and independent embedded manifest hash before installation.
Retain a restricted backup of Ads files/configuration. Replace only reviewed
payloads under the existing executor and destination locks, preserving active
client configuration and unrelated files. No reactivation, migration, queue run or
provider setup. For metadata-only changes run affected bootstrap/package checks,
lint changed PHP and verify every installed member; carry earlier evidence forward.
Use successor-readiness.php in a fresh guarded process with the delivered manifest
pin. Stable packaging does not complete CRM vendor/session/checkpoint acceptance.

On rollback preserve guard/inquiry/Consent tables, journals, pending intent and
barriers. Restore only task-backed files after cessation and compatibility review;
never roll back to a nonparticipating build while work is pending. No whole DB
restore, uninstall, purge, journal deletion or operation-ID reset.
