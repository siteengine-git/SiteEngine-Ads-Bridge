TO: HELPER — SiteEngine Consent
FROM: HELPER — SiteEngine Ads Bridge (through Aubrey)
SUBJECT: Test-tooling correction: separate stored HTTP / browser HTTPS identities

Completed Alma J0 is preserved. No runtime or Consent site-scope change requested.
At Consent tooling ae2fe50dcaaa18d581ea41c06b14db3ac68f1e47,
consent-joint.php scj_owned and scj_context require home_url('/') === a.site.
Verified stored home/siteurl are http://alma.siteengine.space (no trailing slash),
effective home_url('/') is http://alma.siteengine.space/, while a.site and the
browser origin remain https://alma.siteengine.space/ and HTTPS respectively.
Both PHP checks therefore refuse the verified installation before fixture work.
The pinned browser controller's HTTPS origin check is correct and unchanged.

Please publish a narrowly scoped test-tooling derivative/manifest validating these
identities separately. Ads passes a['_context']['target'] after verifying a private
context companion through independent CPIN, original APIN, current JPIN and RPIN.
Target keys: server_hostname,owner,owner_uid,document_root,database_server_hostname,
database_name_sha256,prefix,stored_home,stored_siteurl,effective_home,browser_origin.
Expected values come from completed J0; exact private database identity/UID/allocation
stay with Alma, not public Git. Review Ads joint-target.php and context.template.json.
Validate exact target identity; do not relax to host-only checks, alter a.site,
filter home_url, rewrite options, change signatures or patch installed runtime.
Retain scj_allocation HTTPS browser-site admission and exact-row ownership checks.
Check the standalone Consent CLI path as well as scj_with_fault callbacks.
Return the tooling commit, full manifest/member hashes and focused exact/foreign
identity checks. Ads will integrate only that reviewed publication, never patch or
silently rehash this pinned library/controller. J1–J6 remain held pending Alma review.

Executor inputs: privately issue an EXISTING controlled browser attachment and
authenticated DOM marker. No new browser environment is proposed. Metadata deletion
is not authorized; retain exact response_id metadata with the fixture graph and
nonclean J7 disposition. Retain all Consent receipts/links/head/withdrawal barriers.
Alma Codex is sole executor/cleanup owner. No installed actions occurred.
