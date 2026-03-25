=== GoodConnect ===
Contributors: goodhost
Tags: gohighlevel, crm, gravity forms, elementor, woocommerce, contact form 7, lead generation
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.2.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

GoHighLevel CRM integration for WordPress — Gravity Forms, Elementor, Contact Form 7, and WooCommerce.

== Description ==

GoodConnect connects your WordPress site to GoHighLevel (GHL). When a visitor submits a form or places a WooCommerce order, their details are automatically sent to GHL as a contact — with full support for tags, custom fields, conditional logic, opportunity creation, and multiple GHL sub-accounts.

GoodConnect also receives inbound webhooks from GHL to trigger actions in WordPress — create users, assign roles, send magic login links, and gate content by GHL tag.

= Key Features =

* **Gravity Forms** — map fields to GHL contact fields, custom fields, static and dynamic tags, conditional logic, opportunity creation
* **Elementor Pro** — form field mapping, custom fields, static tags
* **Contact Form 7** — full field mapper, custom fields, static tags, conditional logic, opportunity creation
* **WooCommerce** — sync orders to GHL contacts, configurable trigger statuses, per-product tags
* **Multi-account** — manage multiple GHL sub-accounts, select per-form
* **Bulk User Sync** — sync all WordPress users to GHL in batches via WP-Cron
* **Inbound Webhooks** — receive GHL automation events to create users, update meta, assign roles, or send magic login links
* **Magic Links** — one-time login URLs with configurable TTL for passwordless login via GHL automations
* **Content Protection** — gate pages, posts, and inline content by GHL tag with configurable denied actions
* **Activity Log** — full log of all API calls and webhook events with source and status filters

= External Services =

This plugin connects to the GoHighLevel API to create and update CRM contacts, look up contacts, create opportunities, and fetch custom field definitions.

Data is transmitted to GoHighLevel's servers whenever a configured form is submitted, a WooCommerce order is placed, or a bulk sync is initiated. The API endpoint used is `https://services.leadconnectorhq.com`.

**GoHighLevel Terms of Service:** https://www.gohighlevel.com/terms-of-service
**GoHighLevel Privacy Policy:** https://www.gohighlevel.com/privacy-policy

You must have a GoHighLevel account and a Private Integration API key to use this plugin. No data is sent unless the plugin is configured with valid API credentials.

== Installation ==

1. Upload the `good-connect` folder to `/wp-content/plugins/`
2. Activate via **Plugins → Installed Plugins**
3. Go to **GoodConnect → Settings** and click **+ Add Account**
4. Enter your GHL API key and Location ID

= Getting your API key =

1. In GoHighLevel, go to **Settings → Private Integrations**
2. Click **Create private integration**
3. Enable scopes: `contacts.readonly`, `contacts.write`, `locations/customFields.readonly` (and optionally `opportunities.readonly`, `opportunities.write`)
4. Click **Create** and copy the key

= Finding your Location ID =

Go to **Settings → Business Profile** in GoHighLevel — the Location ID is shown at the bottom of the page.

== Frequently Asked Questions ==

= Does this plugin work without Gravity Forms? =

Yes. GoodConnect also integrates with Elementor Pro, Contact Form 7, and WooCommerce. Each integration is independent.

= Are tags additive or replaced? =

Tags sent via form submissions are additive — they are added to any existing GHL tags on the contact. Tags are never removed by form submissions.

= Can I use multiple GHL sub-accounts? =

Yes. You can add multiple accounts in the Settings tab and assign each form to a specific account.

= Is an SSL certificate required for webhooks? =

Yes. The inbound webhook endpoint requires HTTPS in production. A warning is shown in the admin if SSL is not active.

= How are magic links secured? =

Each magic link token is a 64-character cryptographically random hex string, stored as a single-use user meta entry with a configurable TTL (default 24 hours). Tokens are deleted after use or expiry.

== Screenshots ==

1. Settings tab — add and manage multiple GHL accounts
2. Gravity Forms tab — field mapper, custom fields, tags, and conditions
3. Webhooks tab — inbound webhook URL and event rules
4. Activity Log — full history of all API calls and events

== Changelog ==

= 1.2.1 =
* GHL Custom Fields sync — Load from GHL button on Gravity Forms, Elementor, and Contact Form 7 tabs
* Fetches all custom field names from the GHL API for the selected account
* Results cached per-account for the page session

= 1.2.0 =
* Contact Form 7 integration — field mapper, account selector, static tags, custom fields, conditional logic, opportunity creation
* Conditional logic engine — AND/OR rules with 8 operators
* Opportunity creation — GF, Elementor, and CF7 can create GHL pipeline opportunities
* WooCommerce enhancements — configurable trigger statuses, per-product tags
* Bulk User Sync — sync all WordPress users to GHL via WP-Cron with live progress
* Inbound webhook receiver — REST endpoint with secret token authentication
* Webhook event rules — generate magic links, create WP users, update user meta, add/remove roles
* Magic links — single-use tokenised login URLs with configurable TTL
* User provisioning — create/update WP users from GHL webhook payloads
* GHL tag-based content protection — protect pages/posts by GHL tag
* [goodconnect_protected] shortcode — inline content gating by GHL tag

= 1.1.0 =
* Multi-account support
* Static and dynamic tags
* Custom field mapping
* Activity log

= 1.0.0 =
* Initial release — Gravity Forms, Elementor, WooCommerce integrations

== Upgrade Notice ==

= 1.2.1 =
Adds Load from GHL button for custom fields on all form tabs.
