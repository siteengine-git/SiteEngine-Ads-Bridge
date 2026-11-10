# FieldEdge OAuth provisioning and first read — November 10, 2026

FE Helper controls FE Codex. AB Helper / AB Codex retain shared AB source integration,
attribution, durable outcomes and exports. This assignment adds independent FE files
only. It preserves the synthetic connector and never deploys a client-site plugin.

## Credential infrastructure decision

Installed main Alma Base2.4.0 was inspected read-only through the existing site
profile/shared WP runner. Credential contract and sodium exist; key_context returns
key_unavailable, both sebase_credentials/sebase_credential_audit tables are absent,
and zero consumer definitions are registered. No credential content was read/output.
No keys, tables, definitions, settings or host permissions were changed.

Base's credentials-1 facility is a suitable later integration boundary: registered
set fields, pure validator, active-only runtime read, encrypted DB material with a
separately protected host key and a write-only administrator Stage/Activate workflow.
It is currently **unprovisioned**, not a working FE entry form. Its pure POSIX host
provider also cannot serve as a Windows local key provider. FE Codex did not create
a new shared key service or initialize another owner's infrastructure to bypass this.

For this bounded local connection, use one Windows DPAPI-encrypted CLIXML bundle
outside the repository. New private directory allows the current Windows user and
SYSTEM only. All three inputs are SecureStrings, including identifiers. Existing
files are never overwritten; unsafe links/permissions and wrong-user decryption fail
closed. Only the new dedicated directory's ACL is set; existing ACLs are not loosened.
This is a one-purpose provisioning script, not a general credential manager or an
automatic fallback from Base authority. No credential source is switched on any site.

## Exact administrator steps

On Dell-8940, sign in as the usual Windows admin account. Open PowerShell and run:

```powershell
& 'D:\SiteEngine\Plugins\.PLUGIN — SiteEngine Ads-Bridge\fieldedge\tools\fieldedge\FieldEdge-Connection.ps1' -Action Provision
```

At the three masked prompts enter, directly from the approved provider application:

1. OAuth Client ID under Auth Settings for `Production_ClimatrolAirLLC`.
2. Its OAuth Client Secret.
3. The FieldEdge tenant ID supplied by the Integrations Team (`target_id`).

Keep values out of ChatGPT, command arguments, screenshots, Git and text files. The
script makes no network request during provisioning. It prints the encrypted file
location only: `%LOCALAPPDATA%\SiteEngine\FieldEdge-Climatrol-OAuth\oauth.clixml`.
The file decrypts only under the same Windows user/machine; it is not a portable
server credential or a recovery backup. Do not copy it into a plugin or deploy it
to Alma. No plaintext, token or credential JSON file is written.

Check status without a provider call:

```powershell
& 'D:\SiteEngine\Plugins\.PLUGIN — SiteEngine Ads-Bridge\fieldedge\tools\fieldedge\FieldEdge-Connection.ps1' -Action Status
```

Tell FE Codex only that provisioning succeeded, never the values. FE Codex can then
execute the already-authorized single connection test. The same test can be run
locally by the administrator:

```powershell
& 'D:\SiteEngine\Plugins\.PLUGIN — SiteEngine Ads-Bridge\fieldedge\tools\fieldedge\FieldEdge-Connection.ps1' -Action Test
```

Each explicit Test makes at most two provider calls. Do not repeatedly run it after
an uncertain/failing result; inspect its safe reason/status first. Existing bundle
replacement/removal is deliberately not implemented. On an error, run Status and
report only its fixed reason code. Do not delete a retained bundle to bypass failure.

## Verified provider contract

The official [Member Authentication Guide](https://docs-dev.fieldedge.com/member-authentication-guide-1981868m0)
documents POST `https://fieldedge.withgobo.com/oauth/token` with form-encoded
client_id, client_secret, target_id and grant_type=client_credentials. Response has
access_token, token_type=Bearer and expires_in (example28800 seconds). API calls use
Authorization: Bearer. The client reads actual expiry, checks elapsed lifetime
before its GET and keeps the token only in process memory. No refresh-token flow,
hard-coded TTL, scope/audience parameter or redirect URI is invented. A blank
Redirect URL does not block this documented client-credentials request.

The single allowed read is GET
`https://api.fieldedge.com/open-api/v1/work-orders?offset=0&limit=1`, documented with
workorders:read and offset/limit pagination. The expected envelope is
total(integer), limit(integer), offset(integer), data(array). Only zero/one records
is accepted. See the official [work-order list](https://docs-dev.fieldedge.com/retrieve-a-paginated-list-of-work-orders-42492423e0).
The report exposes known field types, not customer IDs, addresses or invoice values.
The provider response is bounded to64KiB; no raw response is retained.

The [company-name endpoint](https://docs-dev.fieldedge.com/retrieve-company-name-by-id-42492379e0)
requires company:read, absent from the supplied permissions. It is not called.
The token is requested for the operator-supplied tenant, but account-name identity
is not independently verified. The local environment label is a selection guard,
not provider identity proof. JWT-shaped tokens are not decoded or trusted as local
identity assertions. Successful read proves workorders:read access only; supplied
customers/invoices/leads scopes remain untested. No leads:write or webhook action
exists in the client, even though the application has leads:write permission.

## Transport and secret boundary

OAuthClient.php allows only the fixed token POST and fixed work-order GET. cURL uses
TLS certificate/hostname verification, HTTPS-only protocols, zero redirects,5second
connect and15second whole-request timeouts, an8KiB header cap and64KiB body cap. Windows uses
the existing native CA store. The wrapper adds a40second child-process ceiling.
No automatic retries, persistence, polling, synchronization, writes or exports.

DPAPI values are decrypted only for execution, sent to PHP through redirected stdin,
never arguments/environment variables, and sent to the fixed TLS token endpoint.
PHP logs/display/exception arguments are disabled in the runner; raw child stderr
and provider bodies are not surfaced. Fixed errors and numeric HTTP status are
reported. Tokens and secrets still necessarily exist briefly in process memory;
this does not claim protection from a compromised Windows account or memory erasure.

## Actual verification and limits

*49 new checks*:43 PHP synthetic OAuth/HTTP checks and6 Windows native DPAPI/parser/
status checks. Existing62 foundation +51 Climatrol tests remain separate.
Synthetic tests cover exact token parameters, no redirects/extra API calls, redaction,
token expiry,401/403/429/5xx failures, malformed/oversized bodies and ignored limits.
DPAPI roundtrip used temporary synthetic values, not real provider credentials.
Local cURL also performed a TLS-verified public documentation HEAD:HTTP200,errno0.
This is not OAuth/provider authentication evidence.

At implementation closeout the real bundle was absent: authentication and production
read are **not executed** until provisioned. No live permission or account identity
claim follows from synthetic passes. The script/client will produce the bounded
sanitized connection report when real inputs are available.

Next after successful connection: define and verify one explicit inquiry/lead/job
relationship under FE Helper/AB Helper ownership. No bulk retrieval, full CRM sync,
financial history, contact updates or deployment is authorized by this milestone.
