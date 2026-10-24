# SiteEngine Ads Bridge — repository working rules

Canonical editable checkout:
`D:\SiteEngine\Plugins\.PLUGIN — SiteEngine Ads-Bridge\source`

Origin: https://github.com/siteengine-git/SiteEngine-Ads-Bridge.git
Branch: main. One active implementation writer and one canonical source home.
Sibling extractions and ZIPs are preserved historical material, not editable
masters. Do not move the project, replace its repository or rewrite history.

Before work read this file and STATUS.md. On the Windows workstation also read
`D:\SiteEngine\Agency\AI-Workspace\START-HERE.md`, `D:\SiteEngine\AGENTS.md`
and the parent project's AGENTS.md and complete dated Codex handoff. The full
handoff remains local outside Git because it contains operational context.
Current direct user instructions take precedence over older documents.

## Accepted source and publication

- Accepted runtime: 0.6.3; inquiry schema 2; CRM Ops PHP adapter contract 1;
  configuration tree schema 2. Do not conflate these versions.
- Retained original release ZIP SHA-256:
  `c77c9f2f50aea5bd37ccdd51221e4384c80dbb6fa18da2cd84c265d4f0b8c404`.
- Read docs/BASELINE-IMPORT.md for original archive provenance and the seven
  test/documentation files neutralized for public publication. Runtime source,
  neutral configuration example and Sheet contracts match the accepted ZIP.
- RELEASE-MANIFEST.sha256 is the original archive payload manifest; its seven
  intentional publication differences are documented. Verify the publication
  payload with SOURCE-BASELINE-MANIFEST.sha256. Never call this source import
  a rebuilt, installed or newly accepted plugin release.
- Preserve archives, local work and remote ancestry. No force-push, destructive
  cleanup, unrelated imports, automatic version bump or visibility changes.
- Audit staged contents before every publication. Exclude active client.php,
  secrets/service-account material, credentials, client records, runtime logs,
  database dumps and operational identifiers. Keep neutral examples and
  synthetic tests. Do not stage the parent archive collection or local handoffs.
- Keep STATUS.md current with source/baseline/commit, ZIP hash, actual checks,
  acceptance limits, deployments, holds and next task. Save named accepted
  snapshots after tests and keep exact installable ZIP delivery available.

## Architecture and implementation boundaries

- Ads Bridge owns immutable advertising inquiry history and export ledgers.
  CRM Ops owns operational identities, durable heads/CAS/phases, writer holds,
  workflow eligibility and customer delivery. Neither writes the other's tables.
- One contact may have multiple inquiries. Freeze canonical form/entry and
  ff:{form_id}:{entry_id} attribution; mutable CRM fields cannot rebind history.
- Preserve three trusted PHP adapters and contract 1. Call them outside caller
  transactions. Preserve stored identities/complete retry bodies, locks,
  guarded persistence, durable projection plans/checkpoints and recovery holds.
- Only a matching activation committed acknowledgment correlated to the actual
  current head can satisfy the handshake. Ready, checkpoints and historical
  receipts never authorize enrollment. Do not invent cancellation, compensation,
  automatic recovery workers, REST transport or event listeners.
- Keep holds across form feeds, CRM edits and queued/in-flight writers until
  reconciliation/recovery permits release. Local coordinator tests alone do
  not prove that WordPress integration enforces those holds.
- Preserve configuration precedence and Base ownership of shared facts.
  Other plugins and vendor code require their own assigned implementation.
- Preserve lifecycle rules, authoritative per-inquiry consent, current DNC
  gating, actual invoice values/dates, archived reached-stage candidates and
  independent Qualified/Converted ledger sinks. Never fabricate attribution,
  identifiers, consent, revenue or dates to satisfy a test.
- Google Ads API upload stays disabled. Preserve append-only Sheet contracts:
  Google_Ads_Import 21 columns/index 0; All_Leads 94 with Inquiry ID last.

## Environment and scope

- Alma is the intended integration target. The existing alma-agent alias was
  directly requested and verified in the October 24 reconciliation. Shared
  guidance also documents alma-local. Verify route/user/owner/runtime when
  uncertain; never create credentials or silently switch environment roles.
- Use siteengine-prod for AWS only after reading applicable environment guidance.
  Server access grants no deployment, schema/settings mutation, operational
  transition, export, Sheet/Ads/cron change or customer-message authorization.
- The authorized baseline setup permits source organization, documentation,
  validation, commit and push. Plugin behavior/version and installed sites
  remain unchanged. Later changes require their concrete assigned scope.
- Historical checks and mock/isolated SQL tests are not installed acceptance.
  Record actual executed checks, failures/skips and runtime prerequisites.
  Never claim live repeat-inquiry acceptance from an import or dry-run.
