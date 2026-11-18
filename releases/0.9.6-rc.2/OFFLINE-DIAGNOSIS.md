# Offline diagnosis of the approved attempt — November 18, 2026

The initiating failure of the real dispatch remains **unknown**. The original
credential-bearing stdin and exception were not retained. The recovery's OAuth
success and GUID GET 404 do not identify that initiating failure or establish
whether the POST reached FieldEdge. Preserve the existing GUID and consumed
POST/GET claims. Do not replay dispatch or request another POST approval on the
basis of this investigation.

## Demonstrated defects

The original worker at operational commit `90308a6` returned exactly the same
`approved_transport_unconfirmed` response for two injected scenarios:

1. OAuth transport throws before any lead POST invocation.
2. POST returns 201 with the original GUID, then GET returns invalid UTF-8 bytes.
   JSON output serialization throws and the generic catch discards the confirmed
   POST receipt.

Both reproductions used the retained exact approved request body, SHA-256
`13a689235d02dc2a070edb6d8e2918a78d3a2473c26c23f9470f80410919a1ae`,
with fake credentials and injected transports. The retained offline input files
are reconstructed claim wrappers, **not** the original failed secret-bearing
stdin. These reproductions prove evidence loss and ambiguity, not the historical
trigger. No real request was made.

The launcher also discarded stderr and parsed stdout before persisting a receipt;
malformed output, a crash or timeout could therefore lose useful process evidence.

## Correction and interpretation

The production worker handler now records fixed phases, safe failure codes,
HTTP statuses, and transport invocation counts. OAuth token bodies, credentials,
exception messages and traces are excluded. POST/GET diagnostics retain response
lengths and digests. Counts mean calls to the transport function; they do not
prove that bytes reached the provider. A lost process still has unknown counts.

A confirmed creation checkpoint survives later handled failures. GET bytes cross
the private worker protocol as bounded base64, avoiding UTF-8 serialization loss.
The native status helper decodes those bytes before the existing status validator.
Base64 is byte encoding, not redaction: these response receipts remain private.
Neither validation, approved request limits nor automatic-retry behavior changed.

The launcher persists a private receipt before AB result handling, including
invalid-output and timeout paths. Receipts include child exit/timeout metadata
and stdout/stderr UTF-8 text lengths and hashes, without raw stderr. These are
hashes of captured text, not guaranteed original binary stream bytes. Launcher
failures expose only fixed phases and worker-start/stdin-completion flags.

## Focused verification

- 138 assertions across 21 worker scenarios using the retained exact body:
  input/binding/credentials, OAuth rejection/format/TTL, POST transport and HTTP
  failures, malformed/mismatched success, GET failure/404/invalid UTF-8, and bounds.
- 44 PowerShell protocol/process assertions: real offline child crash, malformed
  stdout, held exit, timeout, exclusive durable receipts, redaction, nested input,
  plus PHP-to-PowerShell phase/checkpoint/base64 round trips.
- Existing 29 approved transport assertions passed.
- Six affected PHP files linted; three PowerShell files parsed. Actual production
  CLI malformed stdin returned `held/input/json_invalid`, exit 2, before transport.
- The original private card and both retained offline input files retained their
  before-investigation SHA-256 values. No original receipt was overwritten.

Structured evidence is in `OFFLINE-DIAGNOSIS.json`. Private input and reproduction
material remain outside Git. No DPAPI credential bundle, server command, OAuth
request, provider request, claim mutation, form change or stable delivery operation
was performed during this investigation. Gate A acceptance was not repeated.

## State and remaining blocker

Implemented: yes, source correction. Packaged: no new package. Installed: no new
installation; last verified runtime remains 0.9.6-rc.2 at `3aa1cf5`. Verified:
offline fault/protocol checks only. Released: no new release; stable 0.9.5 unchanged.

Live lead creation remains unconfirmed and the first failure's exact cause cannot
be recovered from the retained generic receipt. These changes improve subsequent
evidence; they do not retroactively establish the real POST outcome or authorize
any further request. No new POST approval request is made.
