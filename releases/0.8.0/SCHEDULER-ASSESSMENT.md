# 0.8.0 scheduler reconciliation — 2026-11-05

Assessment: satisfactory for disabled Ads delivery; no scheduler repair justified.
The tested ZIP and manifest remain unchanged. No jobs, exports, scheduling writes,
metadata resets or global restoration were performed for this assessment.

## Exact option changes

The retained post-acceptance comparison identified these options:

| Option | Meaning / conclusion |
|---|---|
| `_fc_last_funnel_processor_ran` | FluentCRM processor bookkeeping; not a job definition |
| `_fc_last_sequence_run` | FluentCRM sequence bookkeeping; not a job definition |
| `_fcrm_last_email_process_cleanup` | FluentCRM cleanup bookkeeping; not a job definition |
| `_fcrm_last_scheduler` | FluentCRM scheduler bookkeeping; not a job definition |
| `action_scheduler_lock_async-request-runner` | Action Scheduler runner lease/lock option; not a WordPress cron definition |
| `cron` | Serialized WordPress event timestamps, hooks, schedules, intervals and arguments |
| `seab_version` | Expected Ads installation metadata update to 0.8.0 |

A new read-only comparison also found `_fcrm_last_five_minutes_run` and
`acf_site_health` changed since the original baseline. These are separate vendor
bookkeeping/site-health options, not evidence of an added Ads job. The old values
were hashed, not retained, so neither their exact value changes nor the responsible
background actor are established. No unrelated option was reset.

## Historical evidence and limits

The retained installation backup contains option names and SHA-256 digests; it did
not capture the old serialized `cron` payload. Plugin-file backups do not supply
that missing database value. Exact pre-installation hook/schedule/argument and
timestamp comparison is therefore unavailable. We cannot prove that this original
hash change was only timestamp advancement, or reconstruct removed historical jobs.
The separate October J0 cron comparison covers a different window and is not reused
as proof for November acceptance.

The retained end-of-acceptance hook inventory and the fresh inventory both contain
the same 27 hooks, one event per hook: no hook/count additions or removals between
those two inventories. The earlier inventory did not retain schedule/argument
values, so equality of those fields across the window is unknown.

## Current independent assessment

Read-only snapshot at 2026-11-05T19:56:52Z: 27 events, 4,781 serialized bytes;
all recurring schedule names are registered, and every event's stored argument key
matches its actual arguments. Exact timestamps, intervals, argument digests and
callback source identities are retained privately. No argument contents published.

No scheduled event has an Ads hook or a callback originating in Ads Bridge.
Current events belong to WordPress and existing FluentCRM, Fluent Forms, LSCache,
ACF, Call Bridge, Site Shield, Consent and Harbor integrations. Callback reflection
could not identify every callback; unknown identity remains unknown, not an Ads
callback exemption. Hook names and source inspection independently show that Ads
does not register export cron events.

Source review: Ads has no `wp_schedule_event`, `wp_schedule_single_event` or
unscheduling calls. Its importer adds only request-local scheduling/async-runner
suppression. Its Advanced page prints an optional ten-minute CLI instruction;
printing that instruction does not install an operating-system crontab entry.
The CLI's canonical export entry point remains an explicit operator invocation.

Fresh configuration: Sheets, Google Ads, lifecycle and all four export families
are false. Doctor correctly reports Scheduled export runtime as Disabled and says
not to schedule/run exports to clear that state. No successful run is fabricated.

Read-only `/usr/bin/crontab -l`, executed as the existing Alma site owner, exits 1
with the explicit no-crontab diagnostic and no statements. This is distinct from
WordPress's `cron` option. Root/shared crontabs were not inspected or changed, and
the task made no operating-system scheduler/configuration writes. No historical
OS-crontab comparison or comprehensive shared-wrapper exclusion is claimed.

Conclusion: current Ads scheduling is consistent with disabled exports. There is
no demonstrated assignment-caused job change to correct. The missing historical
payload is a recorded coverage limit, not an ongoing scheduler recovery gate.

## Producer gate — concrete remaining release blocker

Fresh read-only invocation of installed CRM 0.3.16's existing
`SiteEngine\CRM\AdministratorSetup::pins()` at 2026-11-05T19:58:02Z fails:

`ads_successor_pin_mismatch:siteengine-ads-bridge.php`

Installed gate source SHA-256:
`0ec876577445b13c5f918ab5ddfbd8fde7d3acb1f06f67d59a313fbf8943d6dd`.
No producer export, import or runtime edit was invoked. Local CRM published main
still resolves to 7da32a653cc0453ace4f623aadeb1ed21e632918, whose coexistence result
is for Ads 0.7.0. No final 0.8.0 disposition was available in those records.
The prepared CRM-owner handback remains applicable. CRM owns the corrected
candidate-specific binding/disposition; Ads does not silently patch it or relabel
old coexistence evidence. Stable publication awaits this specific producer gate.

## Preservation and delivery

Implemented, packaged, main-Alma installed and Ads-verified evidence carries forward.
No repeat import/filesystem/browser campaign. Package:
`47e9f25cc430a78d49df7d69ab0fe4114a0a3dd0c384152889ad02f8d6563f58`;
manifest: `9a661852d731ae1b84afec6e825b03b0c8407b74b11a38550b229e27d1e59fe2`.
Physical Plugins/..Deliverables/PLUGIN — SiteEngine Ads-Bridge contains exactly one
installable ZIP. Historical packages and private evidence remain preserved.
