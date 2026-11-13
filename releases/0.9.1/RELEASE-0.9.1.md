# SiteEngine Ads Bridge 0.9.1

Issue #3: the normal Set up CRM requirements utility now provisions one combined Ads and FieldEdge contract: three lists, four lifecycle tags, a separate Lost outcome-summary tag, and twelve contact fields. FE fields come directly from CRMProjection::definitions(): se_fe_identity (text), se_fe_customer_json (textarea), se_fe_jobs_json (textarea).

Every definition is preflighted before writes. Compatible existing definitions and edits are retained; malformed, duplicate and conflicting definitions block creation. API Setup is read-only and links to the single main setup utility. Lost remains distinct from Unqualified; no loss inference or automatic tagging is added.

Installed with scoped backup on Climatrol development, PHP 8.4.27; 78 members matched and 50 native lints passed. Three ordinary authenticated setup actions succeeded. All terms/fields already existed, so no native definitions were created. The isolated post-login setup action preserved all captured native tables, definitions, settings and journals exactly. Authentication itself advanced FluentCRM login/activity metadata; the initial strict mismatch is retained in RESULTS.json and private evidence.

No importer, marketing workflow, enrollment, email, FE sync, Google/provider request or scheduler was run. Existing configuration and delivery holds remain intact. Temporary guard removed, maintenance absent and wp-config byte-exact. Fresh/conflict branches are local doubles, not destructive live-fixture acceptance. Stable GitHub promotion was not performed.

Runtime/source: f2f6720bf057db6f433e91a654d5903b7f650752
ZIP SHA-256: a70d0f573685db952f1591f7a1c793fe3e0ccabf8f83f186893f50e5baff027d
Manifest SHA-256: 170adab74f5dde4e84d8f14058176695a6e1e6130bac39425b7d4537cfb10b91

This external closeout supersedes unaccepted/candidate wording embedded in the unchanged ZIP. Do not rebuild solely to change evidence wording.
