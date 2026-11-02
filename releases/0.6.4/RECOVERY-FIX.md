# Completed receipt before later inflight step

The actual Alma/native test completed effect one, durably began effect two, wrote
effect two and threw a controlled lost-response exception. The journal retained
one's completed receipt and two's inflight intent; both effect rows existed once.
The first fresh-process recovery failed its completion assertion. Its journal,
effect rows and failed evidence were preserved without reset or resubmission.

Inspection identified the ordering check in `Coordination::step`: it rejected any
step other than the inflight step before permitting completed-receipt readback.
Correction checkpoint `1c570daf52ca84c06e8226262e848b9a1aac7a99` exempts only an
already-receipted step from that ordering veto. The existing exact hash readback
still applies. Drift, uncompleted other steps and unknown creates remain blocked.
No completed or uncertain step is blindly saved again.

Six focused SQL-double assertions reproduce the failure and cover the correction.
After correction, a separate native PHP process recovered the identical request
from the preserved journal. Actual write callbacks ran zero times, both receipts
verified and the journal became complete. Create/interruption/recovery phases
passed 9/12/10 native assertions. Original prefix restoration was checked each time.

This is actual installed Ads/native MariaDB behavior with synthetic effects in
three collision-checked, marked test tables. It is not a forced-OS-crash, physical
power-loss or CRM vendor/checkpoint acceptance claim. Test tables, both journals,
failed evidence and exact request are retained under Ads Codex cleanup ownership.
The original CRM guard, inquiries, Consent evidence/barriers and accepted browser
exercise were not repurposed. Detailed operational IDs remain local/private.

Final implementation SHA-256:
`cf284aac38f02afc868418750bf1af5d99ef088a4b4af66fc580c1e62bf4f2bf`.
CRM owns review/rebinding of this corrected implementation and final package in
its adapter/profile. No CRM source was edited by Ads.
