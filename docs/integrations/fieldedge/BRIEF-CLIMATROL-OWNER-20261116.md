# Climatrol Air — FieldEdge Integration Progress Report

**Prepared by:** SiteEngine Web Services LLC
**Status date:** Friday, November 13, 2026
**For discussion:** Monday, November 16, 2026
**Environment:** Climatrol development website only — **not a production launch**

## Objective

Connect website estimate/service inquiries and advertising identifiers to Climatrol's FieldEdge customer, job and invoice records. Use proven operational outcomes to update FluentCRM and, only when correctly matched and permitted, improve Google Ads measurement. Keep Climatrol's ordinary business operations and phone/SMS workflows unchanged while the integration is developed.

## Progress demonstrated

**Working in Climatrol DEV**

- SiteEngine successfully connected to Climatrol's FieldEdge API using the credentials already provided.
- Real customer and work-order records were retrieved; one invoice was retrieved through a work order's explicit invoice reference.
- A historical Fluent Forms submission was directly verified against its native FluentCRM delivery log, and the same contact was matched to a FieldEdge customer.
- That FieldEdge customer had three work orders. We retrieved all three individually; FieldEdge reported all three completed, each with an invoice reference.
- The new **SiteEngine FieldEdge Connector 0.1.1** is installed and active on Climatrol's DEV site. All three work-order summaries were synchronized into the customer contact's dedicated FieldEdge fields.
- We confirmed a repeated import does not duplicate the data. The contact's email and subscription status were preserved.
- No customer communications, changes to FieldEdge records, advertising uploads or changes to current CRM Won/Lost tags occurred.

**Under development / not yet accepted**

- An automatic six-hour development poller has been registered, but one independent unattended success still needs verification. Some later authentication attempts failed before an HTTP response.
- We have not proved which of that customer's three completed work orders, if any, resulted specifically from the identified website inquiry.
- Automatic Won/Lost classification, invoice-value conversion reporting, and Google Ads offline uploads remain disabled.
- Commercial multi-customer onboarding is a separate expansion after Climatrol's pilot succeeds.

## Why identifying the exact job matters

The website inquiry already contains Google Ads click identifiers. We can identify the originating website lead and verify its CRM delivery. But a returning FieldEdge customer can have several unrelated work orders. Sending all completed work orders as if they came from one advertising lead would overstate advertising results.

We need one authoritative connection between **the original inquiry and the specific resulting FieldEdge job**. We are asking FieldEdge for its supported method. We can also use an owner-reviewed example from Climatrol to validate that method.

## Decisions Climatrol can provide Monday

1. **One confirmed example:** Identify a real inquiry and its specific FieldEdge work order (if known), preferably with the FieldEdge lead ID or work-order number. SiteEngine can perform a controlled private lookup; no names, phone numbers or customer records need to be placed in GitHub.
2. **What counts as Won or Lost?** Should Won mean scheduled service, completed work, accepted quote, or invoiced sale? What indicates a genuine lost opportunity?
3. **What revenue counts?** Should advertising measurement use invoiced amount, paid amount, or another agreed business value? Decide how canceled work, discounts and refunds are handled.
4. **Controlled pilot approval when ready:** Confirm who at Climatrol can approve a new test inquiry or operational changes if FieldEdge requires a live end-to-end test. No such test will be sent without explicit approval.

No change to the business's existing phone number, live forms, FieldEdge workflow or Google Ads account is being requested as part of this report.

## Monday objective

Agree on one acceptable inquiry -> job example and the business definitions for Won/Lost and revenue. SiteEngine will use those decisions and FieldEdge's API guidance to complete the missing identity link and verify an end-to-end test.

**Do not interpret this report as claiming that automatic sales conversion uploads are already operational.** The real data connection and DEV CRM synchronization are working; job-level causation and production acceptance are the remaining milestones.

## Review links

- Repository: https://github.com/siteengine-git/SiteEngine-Ads-Bridge
- **FieldEdge review branch:** https://github.com/siteengine-git/SiteEngine-Ads-Bridge/tree/review/fieldedge-20261116
- **Start here:** https://github.com/siteengine-git/SiteEngine-Ads-Bridge/blob/review/fieldedge-20261116/FIELD-EDGE-REVIEW-START-HERE.md

The GitHub repository is publicly readable. Please do not post customer personal data or API credentials there.
