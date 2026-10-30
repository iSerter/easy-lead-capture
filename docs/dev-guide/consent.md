# Email Consent (GDPR)

Easy Lead Capture collects and records consent for promotional email in a way that satisfies GDPR, UK PECR, the EU ePrivacy Directive, CASL and similar laws.

> This guide explains how the feature works and why it is built this way. It is not legal advice. Have your privacy policy and consent wording reviewed for your jurisdiction.

## Transactional vs. promotional email

| Email type | Examples | Needs a consent checkbox? |
| :--- | :--- | :--- |
| **Transactional** | Confirmation email, replying to the lead's request | **No.** It is lawful under contract or legitimate interest. You must still tell people how their data is used (the privacy-policy notice). |
| **Promotional** | Newsletters, product announcements, offers | **Yes.** It needs explicit, opt-in consent. |

For this reason, the [confirmation email](confirmation-emails.md) is sent whether or not the marketing box is ticked.

## Configuration

```php
'consent' => [
    'marketing' => [
        'enabled' => true, // default
        'label' => 'Yes, send me news and promotional emails. I can unsubscribe at any time.',
    ],
    'terms' => [
        'enabled' => false, // default
        'label' => 'I agree to the Terms of Service and Privacy Policy.',
        'error_message' => 'You must accept the terms to continue.',
    ],
    'privacy_policy_url' => 'https://example.com/privacy', // default: null
],
```

| Key | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `marketing.enabled` | `bool` | `true` | Shows the optional, unticked marketing opt-in checkbox. |
| `marketing.label` | `string` | *(see above)* | The checkbox text. Make it specific about what the lead will receive. |
| `terms.enabled` | `bool` | `false` | Shows a **required** "I agree" checkbox. Submissions without it are rejected. |
| `terms.label` | `string` | *(see above)* | The checkbox text. |
| `terms.error_message` | `string` | *(see above)* | The validation message shown when the box is not ticked. |
| `privacy_policy_url` | `string\|null` | `null` | An `http(s)` URL. When set, a "Read our Privacy Policy" link is shown under the checkboxes. |

### Design rules (do not change these)

- **The marketing checkbox is never pre-ticked and never required.** Consent that is pre-ticked, or made a condition of the service, is not "freely given" under GDPR Art. 7(4) and Recital 32.
- **Keep marketing and terms separate.** Never fold marketing consent into the terms checkbox.

## What is stored (proof of consent)

GDPR Art. 7(1) requires you to *demonstrate* consent. Each lead stores a record in its JSON `data` column under `_consent`:

```json
"_consent": {
  "marketing": true,
  "marketing_text": "Yes, send me news and promotional emails. I can unsubscribe at any time.",
  "marketing_withdrawn_at": null,
  "terms": true,
  "terms_text": "I agree to the Terms of Service and Privacy Policy.",
  "privacy_policy_url": "https://example.com/privacy",
  "captured_at": "2026-10-30T12:00:00+00:00"
}
```

- `*_text` is the exact wording from your server config at the time of submission. It is never taken from the client, so the record still reflects what the lead agreed to after you change the label later.
- Only `true`, `1`, `"1"`, `"on"` and `"true"` count as ticked, which matters if you post to `/submit` from your own form. Any other value is stored as not consented.
- `marketing: false` is stored when the box was left unticked. This records that consent was offered and declined.
- `terms*` keys appear only when `terms.enabled` is true.
- The lead's IP address and user agent are stored in their own columns on the same row.
- Leads captured **before** this feature existed have no `_consent` record. They show as **Unknown** (`—`) and must **not** be treated as opted in.
- If both `marketing.enabled` and `terms.enabled` are false, no `_consent` record is written.

## Withdrawal

Withdrawing consent must be as easy as giving it (Art. 7(3)). When a lead asks to unsubscribe:

1. Open the admin dashboard.
2. Click the green **Opted in** badge on the lead and confirm.
3. The lead's `_consent.marketing_withdrawn_at` is set, and the badge changes to **Withdrawn**.

Withdrawal is one-way. If the lead wants promotional email again, they have to opt in again through the form, which creates a new consent record.

## Where consent shows up

- **Admin dashboard:** a "Marketing" column (Opted in / No / Withdrawn / —) and a "Marketing Consent" filter. See the [Admin Dashboard guide](admin_dashboard.md).
- **CSV export:** the columns `Marketing Consent` (Yes / No / Withdrawn / Unknown), `Consent Text`, `Consent Date` and `Consent Withdrawn At`. Export with the **Opted in** filter to get a mailing list.
- **Admin notification email:** a "Consent" table.
- **API ping:** the full `_consent` object. See the [API Ping guide](api_ping.md).

## Syncing to your email tool

If you sync leads to a CRM or ESP, only subscribe leads to promotional lists when `_consent.marketing === true` **and** `_consent.marketing_withdrawn_at === null`. Withdrawals recorded in the dashboard are **not** pushed to external systems automatically, so record the unsubscribe in your ESP as well.
