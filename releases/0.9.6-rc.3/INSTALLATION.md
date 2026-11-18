# Climatrol DEV diagnostic installation — November 18, 2026

Implemented, packaged, installed and delta-verified. Development candidate only;
not a stable release or live FieldEdge acceptance. Call Bridge remains on standby.

Runtime source: `c60b93e7006381e37210cdc92ef74f4b4c9e58e0`, continuing the
existing pilot branch and offline correction `14429f4`. Version: **0.9.6-rc.3**.

ZIP SHA-256: `871c71ef4bfca2c29663e1ac1dc9d9a1461e4d3f1f6d2b540d5d50abb52040d5`

Manifest SHA-256: `b48bd220f1ca46da49889e224d59eec1413b6adf135e224170f03e1f0e90ad08`

Main PHP SHA-256: `0dd013137ccffcab8aa66b68d9143ddbcca2671d5b9a2be5eaf1b48d16d2b071`

The versioned ZIP, build record and retained first candidate are in the existing
project's `pilot-diagnostics-20261118` folder, outside stable delivery. The installed
0.9.6-rc.2/3aa1cf5 baseline matched all 93 members before installation. Its ZIP and
private rollback tar remain intact. Canonical stable delivery still contains one
unchanged 0.9.5 ZIP; no stable release/tag or client installation was performed.

## Correction and native compatibility

See `docs/integrations/fieldedge/DIAGNOSTICS.md` for fixed-phase diagnostics,
flushed creation checkpoints before GET, bounded byte-safe GET handling, durable
private receipts and crash-boundary limits. The 40-second child deadline was
insufficient for three sequential 15-second HTTP ceilings. Dispatch now permits
60 seconds (45 HTTP + 15 local margin), recovery 45 seconds (30 + 15). No retries
were added. The historical incident has not been attributed to that deadline.

The actual Windows-to-WordPress delta check found a stale 0.9.6-rc.2 reply-version
pin in LeadGate.ps1. It was corrected to 0.9.6-rc.3 and included in the final package.
The initial candidate/manifest/build evidence are retained as superseded. One
corrected-install attempt was safely rejected by the owned temporary fence due to
its manifest filename substitution; no replacement occurred. The fence was corrected
and the pinned package installed successfully. No unexplained replacement was seen.

## Verification and limits

- Reused prior Gate A/Gate B and 14429f4 evidence: 138 worker, 44 process/protocol,
  29 transport assertions; no broad acceptance restart.
- 18 focused checkpoint assertions and 70 updated process/protocol assertions
  passed locally. The latter includes the existing 44 and new delta assertions.
- All 60 PHP payload members linted; three affected PowerShell files parsed.
- Actual installed WordPress/SQL/PHP 8.4.27: 25 focused assertions passed with
  injected transport, including capability rejection, invalid input, byte bridge,
  checkpoint-before-GET, invalid response and checkpoint-write failure.
- Actual Windows caller -> installed native status helper -> missing-reservation
  refusal passed, using only a fixed nonexistent fixture identity and no claims.
- Installed CLI malformed input rejected before transport. Real Linux child crash
  (exit 7) and killed timeout retained the flushed confirmed-creation fixture frame.
- Independent post-cleanup/post-executor-release readback: all 97 exact package
  members, no extras; 114 checked Base/CB/FE/Consent files unchanged; version and
  manifest match the final candidate. No temporary fence or maintenance remains.

Native tests used injected transport, never FieldEdge. No original inquiry/GUID,
approval, consumer identity, claim or receipt was modified; no new live case was
selected or minted. No OAuth/provider request, customer action, CRM lifecycle or
Google/export operation was performed by this job. This does not prove live API
acceptance, a real lead status round trip, or notification behavior.

## Preservation and closeout

102 preexisting tables were covered. Business tables, original lead/claim/receipt
snapshot, nonheartbeat CRM metadata, wp-config and Google credential file hashes
matched. Original private dispatch/recovery receipt hashes also matched. Settings
and disabled exports remained intact. Three Kadence update caches and one EDD
failed-request cache changed during installation; exact prior values/autoload were
restored using comparisons against current values. The initial strict preservation
failure remains recorded rather than discarded.

Action Scheduler tables/logs, FluentCRM heartbeat metadata, five named scheduler
options and cron advanced during the window. These were retained, not reset. The
cron payload is not byte-equal; its hook-name set is unchanged. Final preservation
PASS concerns protected state with this explicitly reported runtime churn; it is
not a claim that every table/option or historical schedule is unchanged.

Existing Alma executor job is complete/inflight null and independently free.
Windows FE mutex is released. Private backups and evidence remain with their
existing owner; only the task's public installer fence was removed.

Original lead creation and initiating failure remain unknown. See RECONCILIATION.md
for the bounded next decision. No further provider operation is authorized here.
