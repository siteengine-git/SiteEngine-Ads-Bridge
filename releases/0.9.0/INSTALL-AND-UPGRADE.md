# Ads Bridge 0.9.0 setup and upgrade

Setup is one page with one **Save settings** action. Incomplete settings may be
saved. Base owns shared Business Data; Consent's loaded authority and published
policy constant supply the policy requirement. No choice or grant is invented.
Existing internal identities and custom mappings are preserved; new empty internal
identities receive site-derived defaults when saved.

**Set up CRM requirements** deliberately creates only missing mapped lists,
lifecycle tags and standard Ads contact fields, including Unqualified Reason.
It uses FluentCRM models/custom-field APIs under the existing Ads configuration
executor. Unique title/slug and field type/options conflicts stop provisioning.
Existing contact values, subscription state, native content, IDs and journals are
not replaced. Partial successful definition creation remains available for unique
adoption on retry. This action does not enable email, enrollment or advertising.

No CRM builder, ZIP, template, automation journey or campaign is a normal setup
prerequisite. The normal import wizard is removed. Runtime Base/service smartcodes
remain available independently. Original import engine/native code, registry and
historical journals are retained. A real unfinished operation appears only in
Diagnostics and requires its original exact recovery identity/source evidence.
It is never reset to make ordinary setup appear complete.

Select the real lead form. Standard field detection preserves custom overrides;
missing/ambiguous selectors remain explicit. The separate FluentCRM integration
inspection recognizes the vendor `fluentcrm_feeds` / `other_fields` structure and
the exact `{submission.id}` -> `submission_id` mapping, with an integration edit
link. Configuration inspection does not establish actual contact delivery. No
operator attestation or optional contact/submission ID controls remain.

The Sheets connection test uses entered draft ID/path without saving: bounded
credential parsing, the existing shared JWT signer, one token exchange and one
spreadsheet metadata GET, no retries or row writes. It reports read identity only;
write permission is unverified. Raw credentials, tokens and remote error bodies
are omitted. Paths are evaluated by the PHP execution identity, not the desktop.

Google Ads account timezone is separate from Base business timezone. Existing
configuration precedence remains; explicit external authority must be reviewed in
Settings > Detailed diagnostics. The configured account timezone is operator
supplied, not an automatic Google check. Advertising export configuration requires
a valid account timezone. Timestamp instants and original historical records are
not rewritten. Existing conversion builders use this timezone for presentation.

API Setup contains the retained byte-identical FieldEdge read-only diagnostics
supplement (SHA-256 78a7c9bc900dead2f30cfae6b7a47a23d5eb2e8933ffa3d649dcc0ca8b58d7bf).
Its CRMProjection dependency matches canonical source. No provider calls are enabled;
the existing explicit administrator field provisioner is retained in API Setup.
It creates only missing definitions under the Ads configuration executor.
Settings has field overrides, diagnostics, scheduler evidence and lead compliance.
Scheduler instructions are not proof of OS cron installation. Disabled exports
remain intentional. Normal protected additive storage setup is retained; no manual
storage-initialization step is part of normal setup.

## Verification and upgrade boundary

Local tests exercise real product methods and rendered controls with explicit
WordPress/vendor/transport doubles. They are not installed or visible-browser
acceptance. The reviewed development target must be independently confirmed before
installation; do not substitute production `climatrolairga.com`, main Alma or the
previous GeneratePress template. Use the existing shared profile/installer,
targeted file/config backups and request-scoped outbound controls. Compare all
manifest members, lint with the site's PHP, then exercise authenticated visible
save/reload and the setup utility. Compare existing settings, objects and journals.

No schema change or bulk import campaign is required. Roll back only this task's
backed-up plugin files if installation fails; preserve any newly created missing
definitions, original settings and all journals for review. Do not restore a whole
database, delete history or reset uncertain work. Do not replace the current
installable delivery or promote stable until installed acceptance passes.
