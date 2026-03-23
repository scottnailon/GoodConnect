# GoodConnect

**GoHighLevel integration for WordPress — Gravity Forms, Elementor, Contact Form 7, and WooCommerce.**

Developed by [GoodHost](https://goodhost.com.au)

---

## Overview

GoodConnect connects your WordPress site to GoHighLevel (GHL). When a visitor submits a form or places a WooCommerce order, their details are automatically sent to GHL as a contact — with full support for tags, custom fields, conditional logic, opportunity creation, and multiple GHL sub-accounts.

GoodConnect also receives inbound webhooks from GHL to trigger actions in WordPress — create users, assign roles, send magic login links, and gate content by GHL tag.

---

## Requirements

- WordPress 6.0+
- PHP 8.0+
- One or more of:
  - [Gravity Forms](https://www.gravityforms.com/)
  - [Elementor Pro](https://elementor.com/pro/)
  - [Contact Form 7](https://wordpress.org/plugins/contact-form-7/)
  - [WooCommerce](https://woocommerce.com/)
- A GoHighLevel account with a Private Integration API key

---

## Installation

1. Upload the `good-connect` folder to `/wp-content/plugins/`
2. Activate via **Plugins → Installed Plugins**
3. Go to **GoodConnect → Settings** and add your GHL account

---

## Configuration

### 1. Add a GHL Account

Navigate to **GoodConnect → Settings**.

Click **+ Add Account** and fill in:

| Field | Where to find it |
|-------|-----------------|
| **Label** | Any name you choose (e.g. "Main Business") |
| **API Key** | GHL → Settings → Private Integrations → Create private integration |
| **Location ID** | GHL → Settings → Business Profile (shown at the bottom of the page) |

**Required API scopes:**

| Scope | Purpose |
|-------|---------|
| `contacts.read` | Look up existing contacts |
| `contacts.write` | Create and update contacts |
| `opportunities.read` | Look up opportunities *(optional)* |
| `opportunities.write` | Create opportunities *(optional)* |

You can add multiple accounts (e.g. one per GHL sub-account). Mark one as **Default** — it will be used by any form that does not have a specific account selected.

Click **Save Accounts**.

---

### 2. Gravity Forms

Go to **GoodConnect → Gravity Forms**.

Select a form from the dropdown. The following settings appear:

#### GHL Account
Choose which GHL sub-account this form sends to. Leave blank to use the default account.

#### Field Mapper
Map each GHL contact field to a Gravity Forms field.

- Name fields are automatically expanded into subfields (First Name, Last Name, Prefix etc.) for precise mapping.
- Leave a GHL field set to *— Not mapped —* to skip it.

#### Custom Fields
Map additional GHL custom fields beyond the standard contact fields.

Click **Load from GHL** to automatically populate a dropdown of all custom fields from your GHL account — no manual key entry needed. The dropdown is populated per-account, so if you use multiple sub-accounts, the correct fields load for whichever account is selected.

- **GHL field** — select from the loaded dropdown, or add a row first then click Load from GHL
- **Gravity Forms field** — the form field whose value will be sent

Click **+ Add Custom Field** to add more rows.

> The **Load from GHL** button is available on Gravity Forms, Elementor, and Contact Form 7 tabs. Results are cached per-account for the page session.

#### Static Tags
Enter a comma-separated list of tags to apply to every GHL contact created from this form.

Example: `webinar-lead, Q1-2026, wordpress`

#### Dynamic Tags
Select a form field whose submitted value will be added as a tag on the GHL contact.

Example: if a "Service Interest" dropdown has the value `SEO`, the contact will be tagged `SEO`.

Click **+ Add Dynamic Tag Field** to map multiple fields.

#### Conditional Logic
Control whether GHL receives a submission based on form field values.

- Enable the **Conditions** toggle to activate
- Choose **All (AND)** or **Any (OR)** to control how rules combine
- Add rules: select a field, a comparison operator, and a value
- Supported operators: equals, not equals, contains, does not contain, starts with, ends with, is empty, is not empty
- If conditions do not pass, the submission is skipped and logged as `skipped_conditions`

#### Opportunity Creation
Create a GHL pipeline opportunity when a form is submitted.

- **Enable** — toggle on to activate
- **Pipeline** — paste your GHL pipeline ID
- **Stage** — paste your GHL pipeline stage ID
- **Opportunity title** — supports merge tags using `{field_id}` syntax (e.g. `{3}` for field 3)
- **Value** — static dollar amount, or map to a form field

#### Saving
Click **Save Mapping** to save all settings for the selected form.

---

### 3. Elementor

Go to **GoodConnect → Elementor**.

Click **+ Add Form** and fill in:

- **Form name** — must match the form name set in the Elementor form widget exactly (case-sensitive)
- **GHL Account** — which sub-account to send to
- **Field mapper** — enter the Elementor field ID (set in the widget's Advanced tab) next to each GHL field
- **Custom Fields** — click **Load from GHL** to get a dropdown of GHL custom fields, then enter the Elementor field ID next to each
- **Static Tags** — comma-separated tags applied to every contact from this form

Click **Save Mapping** to save.

> **Finding field IDs in Elementor:** edit your form widget → click a field → Advanced tab → the ID field shows the value to use here.

---

### 4. Contact Form 7

Go to **GoodConnect → Contact Form 7**.

All CF7 forms are listed automatically. For each form:

- **GHL Account** — which sub-account to send to
- **Field mapper** — enter the CF7 field name (e.g. `your-email`) next to each GHL contact field
- **Custom Fields** — click **Load from GHL** to get a dropdown of GHL custom fields, then enter the CF7 field name next to each
- **Static Tags** — comma-separated tags applied to every contact from this form

Click **Save Mapping** to save.

> **Finding CF7 field names:** in the CF7 form editor, field names are the text inside the shortcode brackets, e.g. `[text* your-name]` → field name is `your-name`.

---

### 5. WooCommerce

Go to **GoodConnect → WooCommerce**.

Enable the toggle and select which GHL account to use.

#### Trigger Statuses
By default the contact is sent when an order reaches `processing`. You can configure additional statuses (e.g. `completed`, `on-hold`).

#### Fields sent to GHL
- First Name, Last Name, Email, Phone
- Address, City, State, Postal Code, Country
- Source: `WooCommerce`
- Tags: `woocommerce-customer` + `woo-{status}` (e.g. `woo-processing`)

#### Per-Product Tags
Assign additional GHL tags based on which product was purchased. Configure product tag mappings in the WooCommerce settings section.

Click **Save**.

---

### 6. Bulk Sync

Go to **GoodConnect → Bulk Sync**.

Sync all existing WordPress users to GHL as contacts in one operation.

- Select the GHL account to sync to
- Click **Start Bulk Sync** — processes 20 users per WP-Cron run with 100ms spacing to respect GHL rate limits
- A progress indicator shows status, processed count, and error count in real time
- Click **Cancel** to stop mid-run
- Each user is sent with tags: `wordpress-user`
- Completed runs are logged to the Activity Log

---

### 7. Webhooks (Inbound)

Go to **GoodConnect → Webhooks**.

#### Webhook URL
Copy the displayed URL and paste it into GoHighLevel → Automations → Webhook action. The URL contains a secret token for authentication.

Click **Regenerate Secret** to rotate the token (the old URL will stop working immediately).

> Your site must use HTTPS in production. A warning is shown if SSL is not active.

#### Event Rules
Configure what GoodConnect does when GHL sends a webhook event.

| Field | Description |
|-------|-------------|
| **When GHL sends event** | The event type string from GHL (e.g. `ContactCreated`, `OpportunityStatusChanged`) |
| **Do this action** | What to do in WordPress |
| **Extra config (JSON)** | Optional configuration for the action (see below) |

**Available actions:**

| Action | What it does | Extra config example |
|--------|-------------|----------------------|
| `generate_magic_link` | Generate a one-time login link for the user and return it to GHL | *(none)* |
| `create_wp_user` | Create a WordPress user from the GHL contact | `{"role":"subscriber","on_exists":"skip","send_welcome_email":true}` |
| `update_user_meta` | Update a user meta field | `{"meta_key":"membership_level","payload_field":"customField1"}` |
| `add_user_role` | Add a role to an existing WordPress user | `{"role":"customer"}` |
| `remove_user_role` | Remove a role from an existing WordPress user | `{"role":"subscriber"}` |

#### Allowed Roles
Select which roles may be assigned or removed via webhook. Administrator and Editor are always excluded for security.

---

### 8. Magic Links

Magic links are generated via webhook events (see above) and allow GHL automations to send a one-time login link to a WordPress user.

- Links are single-use and expire after 24 hours by default
- Expiry is configurable via the `goodconnect_magic_link_ttl` option (seconds)
- Expired and used tokens are cleaned up daily via WP-Cron
- Login URL format: `https://yoursite.com/?gc_magic=<token>`

---

### 9. User Provisioning

When GHL sends a `create_wp_user` webhook event, GoodConnect can automatically create a WordPress user account:

- **Email** is required — taken from the GHL payload
- **Login** is generated from the email address (de-duplicated automatically)
- **Password** is auto-generated and emailed to the user
- **Role** is set from the rule config (must be in the Allowed Roles list)
- **On existing user:** `skip` (default) leaves the account unchanged; `update` updates the name fields
- **Welcome email:** set `"send_welcome_email": true` to send a custom email with `{first_name}`, `{email}`, `{password}`, `{site_name}`, `{login_url}` placeholders
- **GHL sync-back:** set `"ghl_wp_user_id_field": "<custom_field_id>"` to write the new WordPress user ID back to GHL as a custom field

---

### 10. Content Protection

GoodConnect can restrict access to pages and posts based on the visitor's GHL tags.

#### How it works
1. When a visitor submits a GoodConnect-integrated form, their GHL contact ID is stored in a browser cookie
2. When they visit a protected page, GoodConnect looks up their tags from GHL (cached for 5 minutes)
3. If they have the required tags, access is granted. If not, the configured denied action is triggered.

#### Protecting a page or post
Edit any post or page. In the sidebar, find the **GoodConnect — Content Protection** meta box:

- **Required GHL Tags** — comma-separated list of tags the visitor must have (e.g. `member, vip`)
- **If access denied** — choose:
  - **Redirect to page** — send to the configured denied page (set in GoodConnect → Webhooks → Allowed Roles)
  - **Show message** — display a custom access denied message
  - **Show 403 error** — show a standard WordPress 403 page

Leave Required GHL Tags blank to remove protection from that post/page.

#### Shortcode
Use `[goodconnect_protected]` to protect inline content within a page:

```
[goodconnect_protected tags="member, vip" action="hide"]
  This content is only visible to members and VIPs.
[/goodconnect_protected]
```

| Attribute | Values | Default | Description |
|-----------|--------|---------|-------------|
| `tags` | comma-separated | *(required)* | Tags the visitor must have |
| `action` | `hide` / `message` | `hide` | What to show non-matching visitors |
| `message` | any text | *(default message)* | Message shown when `action="message"` |

---

### 11. Activity Log

Go to **GoodConnect → Activity Log** to view a record of every GHL API call and webhook event.

| Column | Description |
|--------|-------------|
| Date / Time | When the event was processed |
| Source | Gravity Forms, Elementor, Contact Form 7, WooCommerce, Webhook, Bulk Sync |
| Form | Form name / order number / event type |
| Account | Which GHL account was used |
| Email | Contact email address |
| Action | API action performed (e.g. `upsert_contact`, `create_opportunity`, `magic_link_login`) |
| Status | ✓ Success or ✗ Failed (hover for error details) |
| GHL Contact ID | The GHL contact ID returned on success |

Use the **Source** and **Status** filters to narrow results. Click **Clear Log** to wipe all entries.

---

## How Tags Work

Tags sent to GHL via the `/contacts/upsert` endpoint are **additive** — they are added to any existing tags on the contact. Tags are never removed by GoodConnect (except via explicit `remove_user_role` webhook actions which affect WordPress roles, not GHL tags).

Both static and dynamic tags from a single form are merged and de-duplicated before being sent.

---

## Multi-Account Support

Each form (Gravity Forms, Elementor, Contact Form 7) and WooCommerce can be configured to send to a different GHL sub-account. If no specific account is selected, the **Default** account is used.

Accounts are identified internally by a stable ID so renaming a label or rotating an API key does not break any saved form mappings.

---

## Data Stored

GoodConnect stores the following in the WordPress database:

| Option / Table | Contents |
|---------------|----------|
| `goodconnect_accounts` | GHL account credentials |
| `goodconnect_settings` | WooCommerce enable/account/trigger settings |
| `goodconnect_gf_configs` | Per-form field mappings for Gravity Forms |
| `goodconnect_elementor_configs` | Per-form field mappings for Elementor |
| `goodconnect_cf7_configs` | Per-form field mappings for Contact Form 7 |
| `goodconnect_webhook_rules` | Inbound webhook event rules |
| `goodconnect_webhook_secret` | Inbound webhook secret token |
| `goodconnect_allowed_roles` | Roles assignable via webhook |
| `goodconnect_protection_denied_page` | Page ID for access-denied redirects |
| `wp_goodconnect_activity_log` | API call and webhook activity log |

All data is removed when the plugin is uninstalled via **Plugins → Delete**.

---

## Changelog

### 1.2.1
- GHL Custom Fields sync — **Load from GHL** button on Gravity Forms, Elementor, and Contact Form 7 tabs
- Fetches all custom field names/keys from `GET /locations/{id}/customFields` for the selected account
- Replaces manual key entry with a dropdown populated on demand
- Results cached per-account for the page session (no repeated API calls)
- Elementor and CF7 tabs now have a full custom field mapping UI (was backend-only)
- Backwards compatible — existing manually-entered keys continue to work

### 1.2.0
- **Contact Form 7** integration — field mapper, account selector, static tags, custom fields, conditional logic, opportunity creation
- **Conditional logic engine** — AND/OR rules with 8 operators applied to GF, Elementor, and CF7 submissions
- **Opportunity creation** — GF, Elementor, and CF7 can create GHL pipeline opportunities on submission with merge tag support in titles
- **WooCommerce enhancements** — configurable trigger statuses, per-product tags, dedup guard per status
- **Bulk User Sync** — sync all WordPress users to GHL via WP-Cron batching, with live progress in the admin
- **Inbound webhook receiver** — REST endpoint for GHL automation webhooks with secret token authentication
- **Webhook event rules** — configurable actions: generate magic links, create WP users, update user meta, add/remove roles
- **Magic links** — single-use tokenised login URLs with configurable TTL and daily cleanup
- **User provisioning** — create/update WP users from GHL webhook payloads, optional welcome email, sync WP user ID back to GHL
- **GHL tag-based content protection** — protect pages/posts by GHL tag, cookie-based contact ID, 5-minute tag cache
- **`[goodconnect_protected]` shortcode** — inline content gating by GHL tag
- **Content protection meta box** — per-post/page required tags with redirect/message/403 options
- **Webhooks admin tab** — manage webhook URL, event rules, and allowed roles
- **Bulk Sync admin tab** — start/cancel/monitor bulk sync with real-time progress polling
- **Activity Log** — now covers all sources including webhooks, magic links, user provisioning, and bulk sync

### 1.1.0
- Multi-account support — manage multiple GHL sub-accounts, select per form
- Static tags — comma-separated tags applied to every contact from a form
- Dynamic tags — map a form field value as a GHL tag on submission
- Custom field mapping — add rows for GHL custom field keys
- Name subfield support — GF name fields expanded to first/last/prefix subfields
- Activity log — every API call logged to a dedicated DB table
- Activity Log tab — filterable list table with Clear Log button
- WooCommerce account selector and logging
- Elementor account selector and static tags

### 1.0.0
- Initial release
- Gravity Forms → GHL contact upsert with field mapper
- Elementor Pro → GHL contact upsert with field mapper
- WooCommerce order → GHL contact upsert
- Settings page with API key and Location ID
- Tabbed admin UI
