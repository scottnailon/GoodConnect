# GoodConnect

**Route your WordPress form submissions to GoHighLevel or Jobber**

GoodConnect bridges your WordPress form plugins to your CRM. Submissions from Gravity Forms, Elementor Pro, Contact Form 7, or WooCommerce are automatically sent to **GoHighLevel** or **Jobber** — creating contacts, clients, requests, and opportunities without any manual data entry.

[![WordPress Plugin Version](https://img.shields.io/badge/version-1.4.0-blue.svg)](https://goodhost.com.au)
[![License: GPL v2+](https://img.shields.io/badge/license-GPL%20v2%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![PHP 8.0+](https://img.shields.io/badge/PHP-8.0%2B-purple.svg)](https://www.php.net/)
[![WordPress 6.0+](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org/)

---

## How It Works

```
Website Visitor
      |
      v
┌─────────────────────────────────┐
│  WordPress Form Plugin          │
│  Gravity Forms / Elementor Pro  │
│  Contact Form 7 / WooCommerce   │
└──────────────┬──────────────────┘
               |
               v
┌──────────────────────────────────┐
│  GoodConnect                     │
│  Field mapping · Tags · Logic    │
└──────┬───────────────┬───────────┘
       |               |
       v               v
┌─────────────┐  ┌─────────────┐
│ GoHighLevel  │  │   Jobber    │
│ Contact      │  │ Client +    │
│ Opportunity  │  │ Request     │
│ Tags         │  │ Property    │
└─────────────┘  └─────────────┘
```

Each form can be mapped to a different CRM account. Choose your provider per-account — GoodConnect handles the rest.

---

## Supported Form Plugins

| Plugin | → GoHighLevel | → Jobber |
|--------|:---:|:---:|
| **Gravity Forms** | Full field mapping, custom fields, tags, conditional logic, opportunities | Client + Request creation, property address, search existing clients |
| **Elementor Pro** | Field mapping, custom fields, static tags | Client + Request creation |
| **Contact Form 7** | Full field mapping, custom fields, tags, conditional logic, opportunities | Client + Request creation |
| **WooCommerce** | Order → contact sync, per-product tags, trigger statuses | Client creation on order |

---

## GoHighLevel Features

- **Contact upsert** — create or update contacts with full field mapping
- **Custom fields** — auto-loaded from your GHL account (no manual key entry)
- **Static & dynamic tags** — tag contacts from fixed values or form field responses
- **Pipeline opportunities** — create opportunities on submission with merge-tag titles
- **Conditional logic** — only send to GHL when form values match your rules (AND/OR)
- **Inbound webhooks** — receive GHL automation events to create WordPress users, assign roles, update meta, or generate magic login links
- **Content protection** — gate pages and posts by GHL contact tag
- **Bulk user sync** — sync all WordPress users to GHL via WP-Cron batching

## Jobber Features

- **OAuth2 connection** — secure authorisation flow, automatic token refresh
- **Create clients** — full contact details with billing and property address
- **Create Requests** — linked to the client, with form message as assessment notes
- **Search existing clients** — finds returning customers by email to avoid duplicates and enable Jobber workflow routing
- **Auto-add property** — when a known client submits from a new address, it's added as an additional property
- **Phone formatting** — Australian mobile numbers formatted as XXXX XXX XXX
- **Source tracking** — records which page the enquiry came from (lead source attribution)
- **Entry sync** — writes Jobber client and request IDs back to Gravity Forms entry meta

---

## Requirements

- WordPress 6.0+
- PHP 8.0+
- One or more of: [Gravity Forms](https://www.gravityforms.com/), [Elementor Pro](https://elementor.com/pro/), [Contact Form 7](https://wordpress.org/plugins/contact-form-7/), [WooCommerce](https://woocommerce.com/)
- A [GoHighLevel](https://www.gohighlevel.com/) account and/or a [Jobber](https://getjobber.com/) account

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

## Setup

### Connecting GoHighLevel
1. **GoodConnect → Settings** → **+ Add Account** → select **GoHighLevel**
2. Enter your API Key and Location ID (from GHL → Settings → Private Integrations)
3. **Save Accounts**
4. Go to the form tab (Gravity Forms, Elementor, CF7, or WooCommerce) and map your fields

### Connecting Jobber
1. **GoodConnect → Settings** → **+ Add Account** → select **Jobber**
2. In the [Jobber Developer Center](https://developer.getjobber.com/), create a new App
3. Set the Callback URL to the URL shown in the plugin settings
4. Enable scopes: `read_clients`, `write_clients`, `read_requests`, `write_requests`
5. Paste the Client ID and Client Secret, **Save Accounts**, then click **Connect to Jobber**
6. Enable the behaviour options you need:

| Option | What it does |
|--------|-------------|
| **Create a Request** | Creates a Jobber Request for each form submission |
| **Search existing clients** | Finds returning customers by email before creating a new client |
| **Add new property** | Adds a property when the submitted address is new for that client |
| **Format AU phone** | Formats phone numbers as XXXX XXX XXX |
| **Track source URL** | Records which page the form was submitted from |

### Mapping Forms
Each form plugin has its own tab in GoodConnect. Select a form, map your form fields to CRM fields, configure tags and conditions, then save. Each form can target a different CRM account.

---

## Jobber API Notes

Jobber's API creates all new clients as **Leads** (since September 2024). Requests linked to lead clients appear under Clients → Leads rather than the Workflow → Requests section.

When **Search existing clients** is enabled, returning customers are matched by email. Their existing (non-lead) client record is used, and the request appears in the Workflow as expected.

This is a Jobber platform limitation — not something the plugin can work around for first-time enquiries.

---

## Building a Release

```bash
./build.sh
```

Creates `good-connect-{version}.zip` ready for distribution or WordPress.org submission.

---

## External Services

This plugin sends form submission data to third-party CRM services:

**GoHighLevel** — `https://services.leadconnectorhq.com`
[Terms of Service](https://www.gohighlevel.com/terms-of-service) | [Privacy Policy](https://www.gohighlevel.com/privacy-policy)

**Jobber** — `https://api.getjobber.com/api/graphql`
[Terms of Service](https://getjobber.com/terms-of-service/) | [Privacy Policy](https://getjobber.com/privacy-policy/)

---

## Changelog

See [readme.txt](good-connect/readme.txt) for the full changelog.

---

## License

GPL-2.0-or-later — see [LICENSE](good-connect/LICENSE)

Developed by [GoodHost](https://goodhost.com.au)
