# Climatrol FieldEdge pilot — execution authority (2026-11-18)

**Canonical execution record:** [Issue #6](https://github.com/siteengine-git/SiteEngine-Ads-Bridge/issues/6). This file describes the controlled pilot branch, not a successful deployment.

## Single owner

- **Integration Control (ALMA)**: scope, acceptance, cross-plugin sequencing and shared executor ownership.
- **Primary AB Codex**: sole source writer and installer for the pilot; native AB inquiry authority, FieldEdge lead GUID/dispatch/status integration, tests, release/evidence. Only AB Codex accepts work on this branch.
- **FE Helper/Codex**: read-only provider/code reviewer. Its current source is reference-only; it must not edit the AB pilot branch or issue new implementation instructions.
- **Climatrol owner/operator**: chooses the controlled real test lead, approves any FieldEdge POST side effects and business stage policy.
- **Alma executor**: normal dev site backups/install/verification/restore under one holder. No generic extra approval loops.

## Sources

- This `pilot/climatrol-fe-lead-guid-20261118` branch starts at accepted AB 0.9.5 `main` (do not merge unrelated FE branch history).
- FE frozen reference: `review/fieldedge-eod-20261118` at `9384d5ea5f5110241dd2da44bc5c1b6e4105a19f`; code under separate Windows `fieldedge` worktree.
- Climatrol DEV currently reported at AB 0.9.1 plus stand-alone FE Connector 0.1.1. Confirm live runtime before any site mutation.
- Provider-confirmed on 2026-11-18: caller-selected GUID `trackingId` becomes `leadId`; duplicate POST same GUID returns HTTP 400; exact GET by original GUID retrieves statuses. **No lead-to-work-order crosswalk**; no verified individual payment transactions.

## Stop designing; implement this acceptance chain

1. Current Climatrol DEV **form 40** inquiry (not historical form 39 entry 889) is persisted with canonical AB identity, click IDs and source evidence.
2. One immutable tenant-scoped GUID is durably reserved against that inquiry **before** dispatch, along with exact request digest; collisions, replay and uncertain outcomes cannot create a second lead.
3. A disabled-by-default, tightly bounded lead POST transport is implemented through the accepted private credential path. It remains blocked pending approval of one specific real test lead.
4. Provider GET for the same original GUID is recorded as an **append-only status observation**, without overwriting the earlier lead-create result. Unknown status, mismatch or regression holds.
5. Any FluentCRM stage update requires an explicitly agreed business mapping and exact identity; preserve unrelated values, DNC, subscriptions, and disabled sending.
6. Google Sheets conversion **dry-run only** requires the original inquiry's actual consent/click-ID/export rules; no invented revenue or Google Ads submission.
7. After installation and independent replay/uncertain-result checks, demonstrate a successful unattended pass (existing Windows Task Scheduler is InteractiveToken and has intermittent result 2).

**Gate A:** native installed dry-run/SQL + exact GUID, replay, duplicate 400 reconciliation, no provider POST. Report exact ZIP/hash/runtime and install evidence.

**Gate B:** prepare the one real external FieldEdge test for operator approval (test contact/appointment/service effects). No new real POST until specifically authorized.

**Gate C:** live one POST + GET + staff-updated status + durable readback + conditional CRM stage.

**Gate D:** Ads dry-run, no repeat provider/CRM effects, reliable unattended execution.

Existing real customer/work-order CRM projection in FE Connector 0.1.1 remains preserved. It is not proof that a particular inquiry generated a specific work order; revenue attribution requires a separately verified link. Do not block the lead-status pilot on FieldEdge Marketplace approval or missing work-order crosswalk.

Prior [FE issue #1](https://github.com/siteengine-git/SiteEngine-Ads-Bridge/issues/1), [FE issue #3](https://github.com/siteengine-git/SiteEngine-Ads-Bridge/issues/3) and [obsolete PR #2](https://github.com/siteengine-git/SiteEngine-Ads-Bridge/pull/2) are not independent execution orders. Reuse tested FE classes selectively, preserve their entire history. Do not claim branch creation is live acceptance or that issue creation starts a Codex session.

**Current actual status at charter creation:** no native real lead POST, no provider-issued GUID bound to new inquiry, no proven lead-specific status persistence or approved lifecycle transition. The existing read-only FieldEdge→CRM projection is working on DEV.
