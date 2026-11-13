# FieldEdge External Repository Inspection — Sharing Audit

**Audit date:** November 13, 2026
**Repository:** `siteengine-git/SiteEngine-Ads-Bridge`
**Visibility:** **PUBLIC** (confirmed from GitHub repository metadata); do not assume private access controls.
**Review branch:** `codex/fieldedge-climatrol-081-20261106`
**Scope:** Current tracked files and available local Git history; **not** a formal independent penetration test, DLP certification, or legal approval.

## Results

1. The branch contains 310 tracked files at the time of audit, including prior Ads Bridge source and release evidence. The FieldEdge integration source and its standalone 0.1.1 ZIP are intentionally version controlled.
2. The current branch's tracked text was checked for common secret indicators (private-key headers, OpenAI/GitHub/Twilio/cloud-style token patterns, hardcoded bearer tokens and obvious inline client secrets). None of the targeted patterns matched.
3. The local Git history contains 142 commits. Introduced patch lines across the available history (96,501 diff lines) were screened for the same high-signal secret patterns. None matched. This is limited **pattern coverage**, not proof that no nonstandard secret or sensitive personal datum ever appeared in history.
4. The current repo's existing older tests contain example Gmail-address literals for normalization. No `@climatrol` email literal was found in the current tracked tree by exact-domain scan. Synthetic tests do not establish the absence of every possible personal identifier.
5. The 0.1.1 ZIP has three declared source files only; release SHA-256: `33fd49a1e92ccf0f72299d6b03588e535a12b09a82b71fdaaaa93f5703a9bcac`. No private provider response journal or credential file is intentionally packaged.
6. OAuth credentials remain in a Windows current-user DPAPI bundle outside the repository. Live provider findings and WordPress private snapshots are retained off Git. Git ignores `.secrets/`, `.env`, `*.pem`, `*.key`, service-account JSON and other ordinary credential file types.
7. The source and reports do reveal **environmental identifiers and architecture** (development hostname, local tool paths, client organization, selected CRM/form record numbers, source branch/commit and API methods). Treat this as non-secret but client-relevant operational disclosure; the repository is public.
8. `main` is older than the FieldEdge review branch, and the current Climatrol DEV plugin runs Ads Bridge 0.9.1 while this source branch was forked from an older Ads Bridge baseline. Reviewers should follow `FIELD-EDGE-REVIEW-START-HERE.md`, not infer installed versions from root README or default `main`.

## Sharing disposition

**Technical inspection material is curated and linkable** on the review branch. The first-party GitHub source is publicly accessible. Do not attach live customer records, OAuth credentials, invoice amounts, secret tenant values or private support communications to public GitHub issues.

**Restriction:** The audit is not a guarantee that public repository history is free of all confidential information, nor a formal review of Climatrol's confidentiality arrangements. If SiteEngine or Climatrol requires non-public code review, switch to a separate access-controlled project/mirror after an appropriate owner decision. Changing visibility of this long-lived public repository was intentionally out of scope.

## Correct review route

1. Start with `FIELD-EDGE-REVIEW-START-HERE.md` at the root of the FieldEdge branch.
2. Inspect `standalone/siteengine-fieldedge-connector/` and `includes/connectors/fieldedge/`.
3. Run the five listed synthetic test suites without any credentials.
4. Read `releases/0.1.1/RELEASE-CARD.md` for actual DEV acceptance and `docs/integrations/fieldedge/BRIEF-FIELDEDGE-20261116.md` for the remaining technical request.
5. Discuss private tenant-specific cases via a secure direct channel, not a GitHub issue.

**Current disposition:** Ready for scoped technical review of the documented FieldEdge branch; not certified for unconditional publication of all historical internal material or for general customer rollout.
