# GoodConnect

**GoHighLevel integration for WordPress — Gravity Forms, Elementor, and WooCommerce.**

Developed by [GoodHost](https://goodhost.com.au)

---

## Overview

GoodConnect connects your WordPress site to GoHighLevel (GHL). When a visitor submits a form or places a WooCommerce order, their details are automatically sent to GHL as a contact — with full support for tags, custom fields, and multiple GHL sub-accounts.

---

## Requirements

- WordPress 6.0+
- PHP 8.0+
- One or more of:
  - [Gravity Forms](https://www.gravityforms.com/)
  - [Elementor Pro](https://elementor.com/pro/)
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
| **API Key** | GHL → Settings → Integrations → API Keys → + Add Key → Private Integration Key |
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

- **GHL field key** — the custom field key/ID from your GHL account (e.g. `service_type`)
- **Gravity Forms field** — the form field whose value will be sent

Click **+ Add Custom Field** to add more rows.

#### Static Tags
Enter a comma-separated list of tags to apply to every GHL contact created from this form.

Example: `webinar-lead, Q1-2026, wordpress`

#### Dynamic Tags
Select a form field whose submitted value will be added as a tag on the GHL contact.

Example: if a "Service Interest" dropdown field has the value `SEO`, the contact will be tagged `SEO`.

Click **+ Add Dynamic Tag Field** to map multiple fields.

#### Saving
Click **Save Mapping** to save all settings for the selected form.

---

### 3. Elementor

Go to **GoodConnect → Elementor**.

Click **+ Add Form** and fill in:

- **Form name** — must match the form name set in the Elementor form widget exactly (case-sensitive)
- **GHL Account** — which sub-account to send to
- **Field mapper** — enter the Elementor field ID (set in the widget's Advanced tab) next to each GHL field
- **Static Tags** — comma-separated tags applied to every contact from this form

Click **Save Mapping** to save.

> **Finding field IDs in Elementor:** edit your form widget → click a field → Advanced tab → the ID field shows the value to use here.

---

### 4. WooCommerce

Go to **GoodConnect → WooCommerce**.

Enable the toggle and select which GHL account to use. When a new order is placed, the following fields are sent to GHL automatically:

- First Name, Last Name, Email, Phone
- Address, City, State, Postal Code, Country
- Source: `WooCommerce`
- Tag: `woocommerce-customer`

Click **Save**.

---

### 5. Activity Log

Go to **GoodConnect → Activity Log** to view a record of every GHL API call made by the plugin.

| Column | Description |
|--------|-------------|
| Date / Time | When the submission was processed |
| Source | Gravity Forms, Elementor, or WooCommerce |
| Form | Form name / order number |
| Account | Which GHL account was used |
| Email | Contact email address sent to GHL |
| Action | API action performed (e.g. `upsert_contact`) |
| Status | ✓ Success or ✗ Failed (hover for error details) |
| GHL Contact ID | The GHL contact ID returned on success |

Use the **Source** and **Status** filters to narrow results. Click **Clear Log** to wipe all entries.

---

## How Tags Work

Tags sent to GHL via the `/contacts/upsert` endpoint are **additive** — they are added to any existing tags on the contact. Tags are never removed by GoodConnect.

Both static and dynamic tags from a single form are merged and de-duplicated before being sent.

---

## Multi-Account Support

Each form (Gravity Forms, Elementor) and WooCommerce can be configured to send to a different GHL sub-account. If no specific account is selected, the **Default** account is used.

Accounts are identified internally by a stable ID so renaming a label or rotating an API key does not break any saved form mappings.

---

## Data Stored

GoodConnect stores the following in the WordPress database:

| Option / Table | Contents |
|---------------|----------|
| `goodconnect_accounts` | GHL account credentials |
| `goodconnect_settings` | WooCommerce enable/account settings |
| `goodconnect_gf_configs` | Per-form field mappings for Gravity Forms |
| `goodconnect_elementor_configs` | Per-form field mappings for Elementor |
| `wp_goodconnect_activity_log` | API call activity log |

All data is removed when the plugin is uninstalled via **Plugins → Delete**.

---

## Changelog

### 1.1.0
- Multi-account support — manage multiple GHL sub-accounts, select per form
- Static tags — comma-separated tags applied to every contact from a form
- Dynamic tags — map a form field value as a GHL tag on submission
- Custom field mapping — add rows for arbitrary GHL custom field keys
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
