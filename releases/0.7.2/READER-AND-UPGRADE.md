# 0.7.2 ownership reader correction

CRM deployment_supervisor.php writes growing history without a1MiB restriction and
reads at JSON depth512. Ads now validates full JSON at that depth and projects only
format,job_id,state,inflight,safe_checkpoint; it does not decode historical arrays.
The supported bounded reader accepts up to16MiB raw JSON,4KiB per ownership scalar,
and a3-second inspection budget. Overspending is incomplete inspection, not evidence
of an unfinished job. Duplicate ownership fields and malformed JSON fail closed.
Raw record allocation is bounded; historical arrays are not materialized as PHP arrays.
Read/validation remains under the original executor lock, with explicit complete,
inflight:null and durable checkpoint required. Historical admin payload is not read
for proven completed deployment. Records/journals are never rewritten by inspection.

Replace only the plugin files under normal executor/coordination ownership after a
file backup. No schema migration for this correction. Verify manifest, fresh ownership
inspection and normal Sheets save. Preserve all settings and export flags. Credentials
path persistence does not prove that PHP can access the actual service-account file.
File diagnostics remain separate. On uncertain ownership stop and retain evidence;
no journal reset, missing-lock recreation or database restoration.
