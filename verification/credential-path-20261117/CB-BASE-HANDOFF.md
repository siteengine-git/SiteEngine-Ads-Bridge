**TO:** CB Helper / CB Codex; Base Helper / Base Codex
**FROM:** AB Helper / AB Codex
**SUBJECT:** Alma Sheets credential path corrected; CB/Base resolution preserved

Information-only closeout for Aubrey to relay. No new CB/Base operation or credential change is requested; no tool-delivered cross-thread message is claimed.

Affected site: https://alma.siteengine.space, owner almas9110 / UID5004, actual web PHP8.3.36/LiteSpeed; CLI PHP8.3.36. Installed Ads0.9.1 uses WordPress settings as authority. Effective and saved path pointed to /home/siteengi/.secrets/se-ads-sa.json. Parent /home/siteengi returns ENOENT on Alma. This is an AWS path, not a Base/Twilio credential path.

Existing Alma file /home/alma.siteengine.space/.secrets/se-ads-sa.json was already a regular, nonsymlink owner5004 file0600 in owner5004 directory0700. Its metadata predates the November16 Base provisioning. AB0.8.2 closeout recorded the same credential-parent failure on November11. Base owner initialization records create /home/alma.siteengine.space/.siteengine-base/credentials.json in a different directory, owner5004/file0600/directory0700. CB/Base provisioning is not the cause demonstrated here.

Backed up raw original Ads settings privately, then changed only google_sheets.credentials_path using the existing configuration executor and supported Settings save/readback. All other settings, original Ads journals/options and wp-config were preserved. No credential file, permission, ownership, plugin source, version, package or provider setting was changed.

Fresh native HTTPS and independent owner CLI both report file-readable/Doctor PASS. CB webhook credential pair and source are unchanged by private keyed fingerprint comparisons; raw values were not emitted. Current external CB configuration and wp-config byte-match CB retained November16 backups. Base encrypted/audit rows and key/directory metadata are unchanged. Sheets JSON and Base key contents were not inspected. No Google authentication, spreadsheet operation, Twilio request, call, export or actual scheduler run was initiated. CLI accessibility does not claim actual cron execution.

Temporary token-protected public endpoint, private request guard, comparison salt and local access token were removed; no maintenance, worker or held executor remains. Configuration guard is complete/inflight null. Original settings backup and private receipts retained under /home/alma.siteengine.space/.seab-diagnostics/ab-credential-20261117-284285b5 (AB custody). Sanitized evidence is RESULTS.json alongside this handoff.
