# Approval-gated lead diagnostics — 0.9.6-rc.3

Development successor to the installed 0.9.6-rc.2. Business payloads, original
inquiry/GUID identity, validation, claim limits and retry rules are unchanged.
The original uncertain case remains held; installing this patch authorizes no
provider request or replay.

The existing Windows DPAPI launcher and installed WordPress private helper remain
the supported path. Use PowerShell 7 with the existing private custody directory;
never run the lead worker manually with real credentials. No new test-mode switch,
public endpoint, credential store or connection route is provided.

The worker emits one small, flushed JSON creation-checkpoint line before GET and
then the final JSON result. The launcher accepts the checkpoint independently of
a malformed, missing or timed-out final result, saves a private exclusive receipt,
and only then invokes native result/status writes. Its failure report retains the
worker receipt path if a subsequent native operation fails. A checkpoint-write
failure prevents GET and returns held with the in-memory creation evidence.

A process killed before it emits the checkpoint can still leave an uncertain
external outcome. This is not an atomic transaction with FieldEdge; no diagnostics
can retrospectively prove delivery in that interval. A parent/workstation loss
before private receipt persistence also requires reconciliation. Never turn absent
evidence into permission to resend.

GET bytes use bounded base64 through the private worker/native protocol. They are
private response data, not redacted by encoding. Diagnostic phases/codes are fixed;
OAuth bodies/tokens, credential values, exception text and raw stderr are excluded.
Captured stdout/stderr text lengths/digests and child exit/timeout are retained.
Transport counters mean callable invocations, not proven network delivery.

Each cURL call has a 15-second total ceiling (including its 5-second connection
ceiling), no redirects and no retry. Dispatch has at most token + POST + GET:
45 seconds HTTP plus 15 seconds local margin = 60-second child ceiling. Recovery
has at most token + GET: 30 + 15 = 45 seconds. The previous 40-second dispatch
ceiling could kill a legitimate 45-second sequence; it is a demonstrated timing
defect, not established as the original incident's cause. Expiry/token checks
remain before each authorized lead request. A timeout never triggers a retry.

Focused acceptance covers injected creation checkpoints before GET, child crash,
launcher timeout, invalid UTF-8, invalid final/GET response, failed checkpoint
emission and a later native status-write exception. Installation verification must
use injected transport and native refusal/readback only, with no provider calls,
new real inquiries, GUIDs, approvals or claims. Reuse prior Gate A/Gate B evidence.
