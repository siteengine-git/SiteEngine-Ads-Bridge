# Climatrol integration operator candidate — November 9, 2026

UNACCEPTED. Stable Ads Bridge 0.8.1 and its delivery remain unchanged. The
integration branch starts at publication `941f74973dabe18d581edd96d0f60a773fbe24ab`,
accepted tag `cd9d5bca7bed9a2fd984d516dc9ae65a703b7d80`. Exact candidate source and
ZIP pins are in `candidates/climatrol-20261106/DELIVERY.json` after packaging.

## What works

One explicitly bound synthetic Fluent Forms inquiry → FE normalization → separate
operator review → durable outcome receipt → fresh advertising eligibility. The
installed FE adapter verifies exact receipt bytes and readback in a fresh PHP
process. USD 1250.00 is explicitly synthetic; it establishes no Climatrol revenue
policy. Native SQL verifies operation replay/conflict, different-notice dedup,
ambiguous/unmatched association, revision/additional-job/invoice holds, Consent/DNC
refusal, two-executor contention and uncertain insert/readback recovery. Business
attribution remains separate from advertising eligibility.

CB 0.1.4 supplies evidence through `CallLogRepository::findByCallSid($sid,true)`.
AB stores `twilio:{CallSid}` and an independent explicit inquiry relation. The
demonstration producer row is synthetic; there was no Twilio call or callback
replay. Call-origin Google delivery holds explicitly. See the single authoritative
[outcome contract](EXTERNAL-OUTCOME-CONTRACT.md) for callables, fields and responses.

## Current installation and pins

The actual URL, database/prefix, owner/root and installed component manifests are
private execution records in the canonical project's sibling
`climatrol-demo-20261106` directory. Use its `site-access.json` through the shared
`Invoke-Site-Wp.ps1`; never substitute main Alma or a client. The installation is
server-loopback only, isolated fresh tables, staging, no customer data/settings
copied, no public vhost change. An owned loopback PHP listener is retained with
PID/start/boot identity for the demonstration; the temporary HTTP admin session was
revoked. It has no public or customer-facing endpoint.

Installed baseline versions: WordPress 7.1.4, PHP 8.3.36, AB 0.8.1 plus candidate,
Base 2.4.0, Consent 0.6.5-rc.1, CB 0.1.4, Fluent Forms 6.2.17, FluentCRM and
Campaign Pro 3.3.1. Exact installed dependency file manifests are recorded privately;
neutral digests/counts are in candidate results. FE foundation branch
`114324fd6c5a1d753265f91e9fe26e483575e273` plus incremental
`70b72248b700f58eb74a915d515f40688b273834` were incorporated selectively. Both FE
branches/worktree are preserved; no old shared STATUS/release history was merged.

## Existing installation demonstration

Use the actual isolated profile and shared runner, with administrator `--user=1`.
Each command is a fresh PHP request. The existing `seab_climatrol_demo_owner` option
contains exact owned IDs, original bodies and receipts. Do not rerun fixture creation
or rotate operation IDs to bypass a receipt. Do not print the option: it includes
private synthetic consent acknowledgments.

1. Inspect: `eval '$s=get_option("seab_climatrol_demo_owner"); echo wp_json_encode(["owner"=>$s["owner"],"inquiry"=>$s["fe_fixture_ids"],"receipt"=>$s["fe_receipt"]]);'`
2. Exact FE readback: set `SEAB_DEMO_MODE=fe-readback` with `putenv()` and require
   `WP_PLUGIN_DIR . '/siteengine-ads-bridge/tools/outcome-demo-acceptance.php'`.
   Expect `recorded`, `receipt_verified=true`, `Attributed`, receipt permission false.
3. Fresh eligibility: `eval '$s=get_option("seab_climatrol_demo_owner"); echo wp_json_encode((new SiteEngine_Ads_Bridge_CLI())->external_outcome_export($s["fe_receipt"]));'`
   Expect `eligible`, 21 values, frozen ff identity, USD 1250.00, personalization Denied.
4. Read the stored call evidence ID from the private execution result. It retains
   twilio identity and AB relationship with `call_origin_google_contract_unsupported`.

## Real test worksheet — not completed

The existing credential path was unavailable. The actual service-account file path
and dedicated test spreadsheet were requested; no credentials were generated for
Google or copied from a customer. No real Google auth/access or worksheet row has
been verified. This is the remaining integration blocker, not an acceptance pass.

After the operator supplies the existing file path and dedicated test spreadsheet:

1. On this isolated installation only, save `google_sheets.credentials_path`,
   `google_sheets.spreadsheet_id`, and `google_sheets.enabled=true` through the
   existing protected settings workflow. Keep all four export families and
   lifecycle automation disabled. Read back in a fresh request and verify CLI/web
   file accessibility without printing credential material.
2. Bind `seab_outcome_demo_target_v1` to the discovered `destination`, that exact
   `spreadsheet_id` and tab `SYNTHETIC_Climatrol_20261109`. Use only a dedicated,
   unconnected test spreadsheet. Preserve any preexisting worksheet/data.
3. Verify Google service-account authentication and target access. Create the
   absent dedicated tab only under this synthetic worksheet scope, with the exact
   21 headers returned by the eligibility preview. No Google Ads account/production
   destination is connected. No ordinary export/import campaign is needed.
4. Execute `se-ads outcome --receipt=<actual FE receipt> --apply` once. Expect
   `exported/synthetic_outcome_appended`. Independently read the test worksheet and
   verify exactly one matching inquiry/Converted action row and all 21 exact cells.
5. Repeat the exact receipt once: expect `already_exported` and unchanged row count.
   A lost/uncertain append acknowledgment must hold. Use `--reconcile` for exact
   readback; zero/duplicate/changed rows require review, never blind reappend.
6. Record the actual Google result privately, freeze exact component/source/ZIP
   pins and preserve the result for Monday. Public stable publication is separate.

## Reproduce on a new authorized isolated installation

Start only with an independently verified staging loopback profile and outbound
containment; never use a main-site URL/prefix. Install the candidate and required
dependencies. Prepare normal Ads storage, choose durable WordPress settings, and
explicitly call `SiteEngine_Ads_Bridge_Outcomes::migrate(true)`. The migration creates
only two absent tables; incompatible existing state is preserved and refused.

With the administrator CLI, require `tools/outcome-demo-acceptance.php` in these
modes using `putenv('SEAB_DEMO_MODE=<mode>')`: `configure`, `fixture`, `fe-outcome`,
then `fe-readback` in separate requests. These are synthetic fixture operations,
not browser form-submission evidence. Do not invoke `outcome` for the main FE
demonstration; it is the separate core-boundary pilot used during implementation.
Negative/concurrency/fault modes are finite one-shot acceptance cases with owned
journals. Do not repeat them on the retained demonstration installation.

`tests/outcome-transport-native.php` separately exercises the actual new exporter
with native WP/SQL and a synthetic HTTP worksheet. It proved one physical append,
lost ACK, held retry, exact readback reconciliation and no second append, including
legacy-dispatch refusal. Its HTTP worksheet is a double, not a Google worksheet.
The ephemeral local signing fixture authenticated nowhere and was removed; isolated
settings were restored exactly. Pilot native ledger evidence is clearly scoped to
the synthetic test-double spreadsheet and is retained separately from the FE case.

## Limits and retained resources

No live FieldEdge fetch/write, customer record, real browser submission, Twilio
call, Google Ads delivery, background worker, REST API, CRM head reset or lifecycle
projection is part of this candidate. One supported Converted result per inquiry
and provider/tenant/job; additional/revised/canceled/refunded economics require
review and do not aggregate or rewrite export history. The reviewed association
is an explicit trusted operator attestation, not verified provider authentication.

The source package retains stable version metadata and is visibly named UNACCEPTED;
it is not a replacement for the stable delivery ZIP. Preserve existing records,
Consent audit and private journals. Own synthetic rows, fixture form/contacts,
readback HTML and listener are retained for Monday; no automatic destructive
cleanup. Temporary contention marker, test signing file and HTTP session are gone.
Only exact owned fixtures may be removed after their demonstration/evidence purpose
ends, with current-value/reference checks. Other assignments retain their resources.
