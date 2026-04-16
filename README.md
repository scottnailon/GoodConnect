# GoodConnect

**GoHighLevel & Jobber integration for WordPress**

Connect your WordPress forms and WooCommerce store to [GoHighLevel](https://www.gohighlevel.com/) and [Jobber](https://getjobber.com/) CRMs. When a visitor submits a form or places an order, their details are automatically sent to your CRM.

[![WordPress Plugin Version](https://img.shields.io/badge/version-1.4.0-blue.svg)](https://goodhost.com.au)
[![License: GPL v2+](https://img.shields.io/badge/license-GPL%20v2%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![PHP 8.0+](https://img.shields.io/badge/PHP-8.0%2B-purple.svg)](https://www.php.net/)
[![WordPress 6.0+](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org/)

---

## Features

### Form Integrations
- **Gravity Forms** — field mapping, custom fields, tags, conditional logic, opportunity/request creation
- **Elementor Pro** — form field mapping, custom fields, static tags
- **Contact Form 7** — full field mapper, custom fields, tags, conditional logic
- **WooCommerce** — sync orders to CRM contacts, configurable trigger statuses, per-product tags

### GoHighLevel (GHL)
- Contact upsert with full field mapping
- Pipeline opportunity creation
- Custom field sync (auto-loaded from your GHL account)
- Static and dynamic tags
- Inbound webhooks — create users, assign roles, update meta, generate magic login links
- Tag-based content protection with `[goodconnect_protected]` shortcode

### Jobber
- OAuth2 connection flow
- Create clients with full contact details + billing/property address
- Create Requests linked to clients (appears in Jobber workflow for existing clients)
- Search existing clients by email (avoids duplicates, enables workflow routing)
- Auto-add new property if address differs from existing
- Australian phone number formatting (XXXX XXX XXX)
- Source URL tracking (records which page the enquiry came from)
- Write Jobber client/request IDs back to Gravity Forms entry meta

### General
- **Multi-account** — manage multiple CRM accounts, select per-form
- **Bulk User Sync** — sync all WordPress users to GHL via WP-Cron batching
- **Magic Links** — one-time login URLs for passwordless authentication via CRM automations
- **Content Protection** — gate pages and posts by GHL tag
- **Activity Log** — every API call and webhook event logged with filters

---

## Requirements

- WordPress 6.0+
- PHP 8.0+
- One or more of: [Gravity Forms](https://www.gravityforms.com/), [Elementor Pro](https://elementor.com/pro/), [Contact Form 7](https://wordpress.org/plugins/contact-form-7/), [WooCommerce](https://woocommerce.com/)
- A GoHighLevel account and/or a Jobber account

---

## Installation

### From zip file
1. Download the latest release zip from [Releases](../../releases)
2. In WordPress admin, go to **Plugins → Add New → Upload Plugin**
3. Upload the zip and activate

### From source
1. Clone this repo
2. Copy the `good-connect/` directory to `/wp-content/plugins/`
3. Activate via **Plugins → Installed Plugins**

---

## Configuration

### GoHighLevel
1. Go to **GoodConnect → Settings**
2. Click **+ Add Account**, select **GoHighLevel** as the provider
3. Enter your API Key and Location ID (from GHL → Settings → Private Integrations)
4. Click **Save Accounts**
5. Go to the relevant form tab and configure field mappings

### Jobber
1. Go to **GoodConnect → Settings**
2. Click **+ Add Account**, select **Jobber** as the provider
3. Sign in to the [Jobber Developer Center](https://developer.getjobber.com/) and create a new App
4. Set the OAuth Callback URL to the URL shown in the plugin settings
5. Enable scopes: `read_clients`, `write_clients`, `read_requests`, `write_requests`
6. Copy the Client ID and Client Secret into the account fields, click **Save Accounts**
7. Click **Connect to Jobber** and authorise
8. Enable the **Jobber Behaviour** options you need:

| Option | Description |
|--------|-------------|
| Create a Request | Creates a Jobber Request for each form submission |
| Search existing clients | Finds existing clients by email before creating (enables workflow routing) |
| Add new property | Adds a new property if the submitted address differs from existing |
| Format AU phone | Formats phone numbers as XXXX XXX XXX |
| Track source URL | Records which page the form was submitted from |

---

## Jobber API Notes

Jobber's API creates all new clients as **Leads** (since September 2024). Requests linked to lead clients appear under Clients → Leads rather than in the Workflow → Requests section.

When **Search existing clients** is enabled and a returning customer submits a form, their existing (non-lead) client record is used and the request appears correctly in the Workflow.

This is a Jobber platform limitation — not something the plugin can work around for first-time enquiries.

---

## Building a Release

```bash
./build.sh
```

Creates `good-connect-{version}.zip` ready for distribution or WordPress.org submission.

---

## External Services

This plugin connects to third-party services to function:

**GoHighLevel** — Contact and opportunity data is sent to GHL's API.
- API: `https://services.leadconnectorhq.com`
- [Terms of Service](https://www.gohighlevel.com/terms-of-service) | [Privacy Policy](https://www.gohighlevel.com/privacy-policy)

**Jobber** — Client and request data is sent to Jobber's GraphQL API.
- API: `https://api.getjobber.com/api/graphql`
- [Terms of Service](https://getjobber.com/terms-of-service/) | [Privacy Policy](https://getjobber.com/privacy-policy/)

---

## Changelog

See [readme.txt](good-connect/readme.txt) for the full changelog.

---

## License

GPL-2.0-or-later — see [LICENSE](good-connect/LICENSE) for details.

Developed by [GoodHost](https://goodhost.com.au)
