# Ads Bridge0.6.4.5 — internal Unqualified event timing

Supersedes the0.6.4.4 unqualified_at contact-field requirement. Install normally;
no activation/schema migration, settings resave, field deletion or historical repair.

Field Mapping / CLI provision-fields no longer create unqualified_at. Doctor and
lifecycle configuration do not require its definition or mapping. Automation still
keeps lifecycle tags mutually exclusive and clears a superseded Qualified milestone.
It never creates or overwrites the retired Unqualified contact custom value.

The existing inquiry tag-add observer records the first observed Unqualified event
in the active inquiry's existing unqualified_at column under the existing contact/
destination locks. Repeat callbacks and projection refresh preserve that event.
An ordinary refresh cannot invent a missing historic timestamp. Existing legacy
contact values remain readable for compatibility/adoption, but cannot overwrite an
active inquiry's internal event. New activation reset plans preserve the retired
contact value; existing frozen plans/journals are not rewritten. An unfinished old
operation must use its exact recovery bytes before upgrading.

All_Leads retains94 columns, including Unqualified at. Inquiry rows supply this
column from that inquiry, including transaction lookup and custom-field aliases;
archived inquiries cannot inherit a later contact projection. Legacy contact-only
history can still read an existing legacy value; no time is fabricated if absent.
Unqualified remains internal: no Google conversion family or API upload is added.
Qualified/Converted retain all existing consent/DNC/revenue/identity gates.

Timing requires an active, properly bound Ads inquiry and initialized existing
storage. This release does not silently adopt contacts, invent inquiry identity,
bootstrap a CRM head or auto-migrate. Doctor/Storage surface missing infrastructure.
The reusable timezone/verification/source/credentials/runtime correction0.6.4.4
remains included. No client installation is accessed by this correction.
