=== GoodConnect ===
Contributors: goodhost
Tags: gohighlevel, jobber, crm, gravity forms, elementor, woocommerce, contact form 7, lead generation
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.4.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

GoHighLevel and Jobber CRM integration for WordPress — Gravity Forms, Elementor, Contact Form 7, and WooCommerce.

== Description ==

GoodConnect connects your WordPress site to GoHighLevel (GHL) and Jobber. When a visitor submits a form or places a WooCommerce order, their details are automatically sent to your CRM as a contact — with full support for tags, custom fields, conditional logic, opportunity creation, and multiple sub-accounts.

GoodConnect also receives inbound webhooks from GHL to trigger actions in WordPress — create users, assign roles, send magic login links, and gate content by GHL tag.

For Jobber users, GoodConnect syncs form submissions and WooCommerce orders directly to Jobber as clients and requests, keeping your field service CRM up to date without manual data entry.

= Key Features =

**GoHighLevel (GHL)**

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

**Jobber**

* **OAuth2 connection** — connect one or more Jobber accounts securely via OAuth2
* **Gravity Forms** — map form fields to Jobber client and request fields
* **Contact Form 7** — sync CF7 submissions to Jobber as new clients or service requests
* **Elementor Pro** — map Elementor form submissions to Jobber
* **WooCommerce** — create Jobber clients and requests from WooCommerce orders
* **Multi-account** — manage multiple Jobber accounts alongside GHL accounts

= External Services =

This plugin connects to the following external services:

**GoHighLevel** — creates and updates CRM contacts, looks up contacts, creates opportunities, and fetches custom field definitions. Data is transmitted whenever a configured form is submitted, a WooCommerce order is placed, or a bulk sync is initiated. API endpoint: `https://services.leadconnectorhq.com`. [Terms of Service](https://www.gohighlevel.com/terms-of-service) | [Privacy Policy](https://www.gohighlevel.com/privacy-policy)

**Jobber** — creates and updates clients and service requests. Data is transmitted when a configured form is submitted or a WooCommerce order is placed. API endpoint: `https://api.getjobber.com`. [Terms of Service](https://getjobber.com/terms-of-service/) | [Privacy Policy](https://getjobber.com/privacy-policy/)

No data is sent to either service unless the plugin is configured with valid credentials for that provider.

== Installation ==

1. Upload the `good-connect` folder to `/wp-content/plugins/`
2. Activate via **Plugins → Installed Plugins**
3. Go to **GoodConnect → Settings** and click **+ Add Account**
4. Choose your provider (GoHighLevel or Jobber) and follow the instructions below

= Connecting GoHighLevel =

1. In GoHighLevel, go to **Settings → Private Integrations**
2. Click **Create private integration**
3. Enable scopes: `contacts.readonly`, `contacts.write`, `locations/customFields.readonly` (and optionally `opportunities.readonly`, `opportunities.write`)
4. Click **Create** and copy the API key
5. In WordPress, paste the key and your Location ID into the account row — find your Location ID under **Settings → Business Profile** in GoHighLevel

= Connecting Jobber =

1. In the Jobber Developer portal, create an app and note your Client ID and Client Secret
2. Set the redirect URI to: `https://yoursite.com/wp-admin/admin.php?page=good-connect`
3. In WordPress, add a Jobber account, enter the Client ID and Client Secret, and click **Connect to Jobber**
4. Authorise the connection in the Jobber popup

== Frequently Asked Questions ==

= Does this plugin work without Gravity Forms? =

Yes. GoodConnect also integrates with Elementor Pro, Contact Form 7, and WooCommerce. Each integration is independent.

= Can I use both GoHighLevel and Jobber at the same time? =

Yes. You can add accounts for both providers and assign each form or WooCommerce trigger to the appropriate account.

= Are GHL tags additive or replaced? =

Tags sent via form submissions are additive — they are added to any existing GHL tags on the contact. Tags are never removed by form submissions.

= Can I use multiple GHL sub-accounts? =

Yes. You can add multiple accounts in the Settings tab and assign each form to a specific account.

= Is an SSL certificate required for webhooks? =

Yes. The inbound webhook endpoint requires HTTPS in production. A warning is shown in the admin if SSL is not active.

= How are magic links secured? =

Each magic link token is a 64-character cryptographically random hex string, stored as a single-use user meta entry with a configurable TTL (default 24 hours). Tokens are deleted after use or expiry.

= What data is sent to Jobber? =

Only the data you explicitly map in the form or WooCommerce settings is sent. Typically this includes name, email, phone, and address fields used to create or update a Jobber client record.

== Screenshots ==

1. Settings tab — add and manage multiple GHL and Jobber accounts
2. Gravity Forms tab — field mapper, custom fields, tags, and conditions
3. Webhooks tab — inbound webhook URL and event rules

== Changelog ==

= 1.4.0 =
* Jobber integration — OAuth2 connection, Gravity Forms, Elementor, CF7, and WooCommerce support
* Jobber multi-account support alongside GoHighLevel accounts
* Jobber client and service request creation from form submissions and WooCommerce orders
* Settings tab updated to support both GHL and Jobber provider types

= 1.3.0 =
* Improved conditional logic engine — additional field operators
* WooCommerce — per-product tag support expanded
* Activity Log — filter by provider and source
* Bug fixes and performance improvements

= 1.2.3 =
* Gravity Forms — Conditions UI now visible and functional in the admin tab
* Gravity Forms — Opportunity creation UI (pipeline, stage, title, value) now available in admin tab
* Elementor & CF7 — Opportunity creation UI added to all form cards
* WooCommerce — Trigger statuses (configurable per order status) and per-product tags UI added
* Load from GHL — existing saved custom field rows now properly replaced with populated dropdowns on all tabs
* Protection meta box — admin JS now correctly enqueued on post/page edit screens
* Settings tab — GHL sign-up link added below API key instructions

= 1.2.2 =
* Fixed Load from GHL — fields now appear immediately after loading even when no rows exist yet
* Fixed Load from GHL — new rows show GHL field dropdown after loading
* Fixed Load from GHL — GET requests no longer send a body (resolves request failures)
* Added locations/customFields.readonly to required API scope instructions

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

= 1.4.0 =
Adds full Jobber CRM integration — connect Jobber accounts and sync form submissions and WooCommerce orders to Jobber clients and requests.
