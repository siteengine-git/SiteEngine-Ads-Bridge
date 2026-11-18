# Consent target-context correction — 2026-10-31

Self-contained handoff for Ads Helper through Aubrey. No external message sent. Derivative of Consent tooling `ae2fe50dcaaa18d581ea41c06b14db3ac68f1e47`; actual publication checkpoint is the named tag `consent-target-context-local-20261031-r4`. J1–J6 remain held. Alma Codex remains the sole server executor/cleanup owner. Completed J0 is preserved; this work performed no Alma inspection, bootstrap or execution.

## Dependency and identity contract

Reviewed Ads commit **09a96e132fe73d02b499dfa9fc3b7ee23d8b2acb**, specifically its handback, joint-target.php and context.template.json. Consent reuses the actual Ads validator, without copying its target settings or creating a second target system:

- `joint-target.php` SHA256 **2b7a5bf897dd22c1ca7a629b88566b4c9f9589e0f9f3eb8a4e2d62cfe191f117**.
- `context.template.json` SHA256 **80cd8f1c264db0821ce61c5385dd4aa87c664e136ca48dc5e446817c1d498d83**. The template is not an execution context; actual UID/database identity and all private pins remain with Alma.

The verified target has stored home/siteurl `http://alma.siteengine.space` without trailing slash, effective `home_url('/')` `http://alma.siteengine.space/`, and separate browser origin `https://alma.siteengine.space`. Allocation site remains `https://alma.siteengine.space/`. These identities are checked independently and exactly; they are not equated or reduced to hostname-only checks. The unchanged Ads validator compares actual server hostname, owner/UID, canonical document root, database server hostname/name SHA256, prefix, stored URLs and effective home with the pinned target. Its exact HTTPS browser identity is bound to allocation site; the unchanged browser controller validates the actual browser origin. No home_url filter, URL normalization or setting rewrite is used.

## Supervised callback path — same callable signatures

Ads continues passing `$a['_context']['target']` inside the verified complete companion object. Required inputs:

```php
$a['_sha256'];         // independently supplied original APIN
$a['_release_sha256']; // independently supplied RPIN
$a['_context'];        // exact decoded private context.json, already CPIN-verified by Ads
$j['context_sha256'];  // CPIN bound into the independently authenticated journal

scj_context($a);
scj_inspect($a, $j, $label);
scj_with_fault($a, $j, $label, $mode, $callback);
```

Consent additionally authenticates the original private allocation against APIN and requires its fields to match the supplied allocation. It authenticates fixture-release.json against RPIN, checks release status/APIN, obtains its bound CPIN, authenticates context.json against that CPIN, and verifies assignment/APIN/full supplied context equality. `scj_owned` also checks journal CPIN. An arbitrary injected target array is insufficient. A missing release/context pin is a refusal, not a fallback to a.site.

All three files must stay at their existing exact filenames beneath the allocated canonical private run directory, 0700 directory/0600 files, no symlink files, with current executor UID matching the allocation and file owners. This is the existing Ads allocation/context/release pin contract. No new context file or private configuration needs to be created by Consent.

If `seab_joint_target` is already loaded by Ads, Consent verifies its reflected source-file SHA256 before using it. Standalone execution requires that same unchanged `joint-target.php` alongside consent-joint.php; it verifies the file before require and the reflected loaded file afterward. Missing or substituted validator code is a hard failure.

## Standalone invocation — now nine arguments

```text
eval-file /approved/private/package/consent-joint.php
    inspect|fault LABEL MODE allocation.json APIN JPIN RPIN context.json CPIN
```

The argument order matches Ads R4. The old seven-argument form is rejected. The standalone parser/context binder accepts an explicit private context path plus independent CPIN/RPIN; it never takes a caller-supplied target array. It verifies context file hash/path/owner/mode, release CPIN binding, allocation binding and actual target identities before any owned entry is read. Existing standalone journal SHA, release, exact-ID validation and shared lock remain required.

Paste-ready canonical-runner argument construction for **Alma's reviewed future execution only**:

```powershell
$Run = '/home/alma.siteengine.space/.se-joint-acceptance/joint-consent-ads-20261031-01'
$Profile = '<existing independently verified profile>'
$APIN = '<independent completed allocation SHA256>'
$JPIN = '<independent current journal SHA256>'
$RPIN = '<independent fixture-release SHA256>'
$CPIN = '<independent private context SHA256>'
$Arguments = @('eval-file', "$Run/consent-joint.php", 'inspect', 'C', 'eligible',
  "$Run/allocation.json", $APIN, $JPIN, $RPIN, "$Run/context.json", $CPIN)
& 'D:\SiteEngine\Agency\AI-Workspace\Invoke-Site-Wp.ps1' `
  -SiteProfile $Profile -TimeoutSeconds 20 -WpArguments $Arguments
```

This syntax does not authorize bootstrap or bypass the retained pre-bootstrap guard and supervised process-group timeout. Run only inside the existing reviewed executor/launcher contract after fixture release. `fault C failed-current-read` uses the same remaining arguments. The supported inspect expectations remain eligible/withdrawn/denied; mode/ID/marker/response/audit validation is unchanged. Callback signatures and the existing browser controller are unchanged.

## Focused evidence and preservation

- **39 target correction assertions** exercise the actual standalone parser/context binder and callback context/owned paths, using the real pinned Ads validator with native OS/WP/file boundaries doubled. Correct HTTP/HTTPS split passes. Wrong actual server, owner/UID, root, database host/name, prefix and stored/effective URLs fail on both paths. Wrong allocated browser origin/database hash/prefix, missing/forged context, journal CPIN, wrong CPIN/RPIN/APIN/path, nonprivate/changed context and old argument shape fail. Callback exception restores the original DB and real authority afterward.
- **32 affected tooling assertions** pass using the shared corrected target fixture. This retains exact-row ownership, all existing fault projections and failure-path restoration coverage. The test fixture's IDs/UID/database name are synthetic; no private J0 values are published.
- Three affected/new PHP test files plus consent-joint.php are linted. No unchanged browser or runtime suite is rerun. Tests do not bootstrap WordPress or execute any SQL/browser action on Alma.

`CONSENT-TOOLING-MANIFEST.json` lists full member hashes and this dependency pin. The browser helper and its tests remain byte-identical to ae2fe50. All five rc.2 runtime members, signatures/continuity scope, original installable ZIP (`8471e4761eef927bb10d44967ab88205ef0890a3b6bf90888b8d1ef2c182b619`), J0 evidence and all audit rows/links/heads/barriers remain untouched. No mutation/resource ceiling or cleanup permission changes. Metadata deletion remains unauthorized; preserve response_id metadata and the fixture graph for the recorded nonclean J7 disposition.

Next owner action: Ads Codex incorporates only this reviewed derivative/member hashes, retains the exact Ads target helper, and passes APIN/JPIN/RPIN/context path/CPIN as above. Alma reviews the combined pins while J1–J6 stay held. No installed acceptance is claimed by this local correction.
