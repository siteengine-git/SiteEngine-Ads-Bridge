# Stable0.9.5 installation and upgrade

Use the exact published ZIP on PHP8.3+. Back up plugin files, active configuration
and database/journals first. Direct native WordPress ZIP0.8.2 to0.9.5 replacement
passed22 assertions in the isolated release fixture; no intermediate Ads version
or destructive migration is required. Existing compatible storage and history are
retained. Incomplete/incompatible storage or unfinished ownership still requires its
supported recovery, never a reset. Back up any legacy plugin-local config outside
the replaceable folder; use controlled file-preserving replacement for that mode.

Base2.6.1 is released and supplies ordinary document Save. CB0.2.3's release is
confirmed. Credential Save, Google authentication and Ads source selection remain
separate. Do not import a real credential or change its authority as part of this
upgrade. Existing File deployments stay File. No live export is part of validation.
See RELEASE-NOTES.md and RELEASE-EVALUATION.json for cron/acceptance limits.

The unchanged package's following build-time credential instructions remain
applicable; references to older stable publication state are historical.

# Google Sheets credentials — Ads Bridge 0.9.5

Base 2.6.1 (accepted Alma source `cdab9f1c9cb29e600b3cf492500f982a854711e4`)
owns protected entry, encryption and ordinary document Save. Ads opts in only when
the exact additive `document-save-1` probe succeeds; older Base versions retain
their existing document controls. Base 2.5.0 remains the published stable baseline. Ads registers module
`ads-bridge`, group `google-sheets`, resource `default`, kind `document`, purpose
`sheets-auth`. The Setup link uses Base's authorized group URL helper. No secret
input/upload, browser secret readback or Media Library route is added to Ads.

## Set up or keep an existing installation

1. Existing installations keep the file source when `credentials_source` is
   absent. Keep the private absolute path in Setup. Page loads and Save settings
   make no Google requests. Saving incomplete setup remains supported.
2. To use Base, follow **Manage Google service-account JSON in Base Credentials**.
   Paste the complete JSON document into the field labeled **Google service-account
   JSON** and click **Save credentials**. The document becomes active in Base's
   protected store; Ads continues using its separately selected source. Ordinary
   administration does not require separate Stage or Activate actions when the
   exact document-save-1 capability is available.
   Ads does not read the legacy file into Base, replace it or change permissions.
3. Save the desired spreadsheet ID and preserved file path in Ads Setup first.
   Select Base as the **source to test**, then **Test connection**. This deliberate
   action requests one Google OAuth token and one spreadsheet-ID metadata GET,
   each bounded to 10 seconds/64 KiB, no redirects or retries. It reads no rows,
   creates no worksheets, appends nothing and never enables exports. Read access
   and authentication do not establish Sheet write permission or Ads eligibility.
4. **Verify and use selected source** performs a fresh test, then changes only
   `google_sheets.credentials_source` under the existing configuration executor.
   Settings and credential identity must still match the verified selection.
   Unsaved target changes, source/configuration changes or active/unfinished
   ownership block the switch. A successful earlier Test is not a reusable permit.
   Reload independently confirms the selected source. A lost response requires
   readback before retrying; it does not prove the source stayed unchanged.

## States and selection

`file` and `base` are the only sources. An absent selector preserves file behavior;
an explicit empty, malformed or unknown selector fails closed. Base's active
document is read afresh through credentials-1 for web and CLI; staged documents,
missing APIs, inaccessible storage/keys, bad format and authentication failures
never trigger file fallback. File reads are bounded to 128 KiB, depth 32, and use
the same service-account/RSA validator. Required JSON fields are type,
project_id, private_key_id, client_id, client_email, private_key and token_uri;
the OAuth endpoint must be Google's and the RSA private key at least 2048 bits.
Local format/key validity does not prove a Google identity/key has not been revoked.

Setup and Doctor show the selected source and local validity/accessibility in
that PHP context; they explicitly do not claim provider authentication. Only the
explicit test reports current authentication/access. It is not cached as readiness
or used to authorize future sends. Existing export enablement, consent, DNC,
inquiry identity, replay protection and 21/94-column contracts are unchanged.
CLI preflights and web tests use the same resolver. Actual scheduler execution
remains a distinct context check; inspecting CLI is not running cron.

Base's group remains available for protected Save while the Ads file source is selected.
That is protected Base document administration, not a transfer of Ads runtime
source authority. Intentional external Ads configuration retains precedence:
WordPress source switching is refused. Its owner must first verify the desired
credential/target, then deliberately set `google_sheets.credentials_source` in
that authoritative configuration; Ads never rewrites that file or imports it.

## Rollback and limitations

Select File, then **Verify and use selected source** to deliberately return to
the retained file/path after fresh verification. The old source label is retained
as `credentials_previous_source`; it is not an automatic recovery policy. No old
credential material, tokens or private fingerprints enter ordinary options,
diagnostics, logs, exports or browser results. Base replacements use its protected Save action with document-save-1;
older Base versions retain explicit stage/activate controls. Ads opts out of Base remove/rollback operations;
it does not purge audit/history, keys or encrypted tables.

Before software rollback to a version lacking this adapter, deliberately switch
to a verified readable file, then verify the old release's selection behavior.
Do not downgrade while Base is selected: old software may ignore the selector.
If that verification is unavailable, preserve state and keep delivery disabled.
Back up plugin files/configuration and retain Base's existing paired encrypted
data/key recovery custody; this adapter creates or removes neither.

The 0.9.5 package also carries the already locally verified 0.9.2/0.9.3 Customer
Rating/Unqualified Reason separation and Estimated Value definition. Their existing
evidence remains distinct; a credential test does not establish a new native CRM
provisioning, lifecycle, browser form or advertising acceptance campaign.
