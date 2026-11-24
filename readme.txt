=== Nomad Site Audit ===
Contributors: costibotez
Tags: security, seo, audit, maintenance, ai
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect WordPress to Codex or Claude for read-only security, search and site health audits.

== Description ==

Nomad Site Audit connects WordPress to Codex or Claude over the Model Context Protocol (MCP). The client can check the live site, explain what it finds and guide you to the WordPress or hosting settings where you can apply recommendations.

It was built for freelancers, agencies and site owners who look after WordPress sites month after month and want the routine checks done faster and explained better.

Nomad Site Audit is developed and maintained by Costin Botez. You can reach the author at contact@nomad-developer.co.uk.

= Security audit =

* Security checks with a plain explanation of each result, covering updates, inactive plugins and unused themes, PHP version, HTTPS and SSL certificate expiry, debug settings, a public error log, the dashboard code editor, wp-config.php permissions, PHP files in uploads, folder browsing, easy to guess usernames, administrator count, open registration, username listing, XML-RPC, browser security headers and version disclosure
* Malware scan for obfuscated code, web shells, encoded blobs, remote includes, PHP hidden in images and recently changed PHP files
* File verification against official WordPress.org checksums for WordPress itself and for plugins hosted on WordPress.org
* Optional known vulnerability checks for plugins with a WPScan token

= Search and speed =

* Search optimisation audit: indexing, sitemap, robots.txt, permalinks, search titles, meta descriptions, noindex, main headings, internal links, image alt text and duplicates. Works with Yoast SEO and Rank Math
* Page check: response time, size, title, description, canonical, headings, images, structured data, render blocking scripts, loading screens, mixed content and response headers
* Broken link finder, including Elementor pages

= Site health and code review =

* Site overview, database health, scheduled tasks, error log and users
* Read only file browsing, file reading and code search for reviewing themes and plugins

= Audit history and monitoring =

* Save manual audit snapshots and pin a baseline to see new, worsened, recurring, resolved, or unverified findings.
* Follow the remediation checklist to the relevant WordPress screen and recheck findings after making changes.
* Download a standalone branded HTML report or preview it and print it to PDF in your browser.
* Enable weekly audit digests for high-priority findings and regressions.
* Monitor up to five public pages daily for response time, HTML size and HTTP status. Optional Chrome UX Report data adds mobile Core Web Vitals (LCP, INP, CLS).

Open Tools, Nomad Site Audit to run your first audit and configure reports, digests, and monitoring. Snapshots cover security, site-wide SEO, SEO checks on the 100 newest published posts and pages, and database health. The plugin retains 52 recent snapshots plus the pinned baseline and 90 performance samples. Scheduled jobs depend on WordPress Cron.

= Connection controls =

* An administrator opens a 15-minute window and approves each new connection.
* The connector grants read-only audit access. Administrators can disconnect individual clients or switch off all access from WordPress.
* OAuth uses PKCE, hashed tokens and refresh token rotation. The activity log records completed audits and errors without storing tool arguments.
* File inspection blocks protected paths such as wp-config.php, .env files, private keys, common backup archives and version control folders. It redacts recognised secret patterns from file excerpts and logs. Review what you choose to share with your client.

Codex and Claude are products of OpenAI and Anthropic respectively. Nomad Site Audit is an independent project.

== External services ==

This plugin connects to the services below. It does not send any data to the plugin author.

= Codex (OpenAI) =

After you approve a connection, Codex receives the results of the audit tools it requests. These can include site settings, plugin and theme lists, content metadata, file excerpts and redacted logs. During approval, WordPress may fetch Codex's client metadata from chatgpt.com to validate the callback address.

* Terms of use: https://openai.com/policies/terms-of-use/
* Privacy policy: https://openai.com/policies/privacy-policy/

= Claude (Anthropic) =

After you approve a connection, Claude receives the results of the audit tools it requests. These can include site settings, plugin and theme lists, content metadata, file excerpts and redacted logs. During approval, WordPress may fetch the client metadata from the configured Claude domain to validate the callback address.

* Terms of service: https://www.anthropic.com/legal/consumer-terms
* Privacy policy: https://www.anthropic.com/legal/privacy

= WordPress.org =

Used only when an audit needs it. The plugin requests plugin details from api.wordpress.org (plugin slugs, to check whether a plugin is up to date, abandoned or closed), official core checksums from api.wordpress.org (WordPress version and language) and official plugin checksums from downloads.wordpress.org (plugin slug and version).

* Terms of use and privacy policy: https://wordpress.org/about/privacy/

= WPScan (optional) =

Only used if you enter a WPScan API token in the settings. The plugin then sends the slugs of installed plugins and your token to wpscan.com to look up known vulnerabilities. Use of the API is subject to WPScan’s terms.

* Terms of service: https://wpscan.com/terms
* Privacy policy: https://automattic.com/privacy/

= External link checks (optional) =

If you request an external broken-link check, the plugin makes HTTP requests to the links found in your published content. It uses the response status to identify broken links and includes the site URL in the request’s User-Agent header.

= Chrome UX Report (optional) =

Only used if you save a Chrome UX Report API key in the audit settings. The plugin sends the chosen public page URLs, your API key, the mobile device category, and requested metrics to Google to retrieve aggregated Core Web Vitals.

* Terms of service: https://developers.google.com/terms
* Privacy policy: https://policies.google.com/privacy

== Installation ==

1. Install and activate the plugin from the WordPress Plugins screen.
2. Open Settings, Nomad Site Audit. Copy the connection address and use Test discovery to check that your host routes the OAuth metadata URL to WordPress.
3. Click Allow a new connection just before connecting from your client.
4. In Codex, add a Streamable HTTP MCP server with the copied URL. From the CLI, run `codex mcp add nomad-site-audit --url https://example.com/nsa-mcp`, using the address from your site. If prompted to authenticate separately, run `codex mcp login nomad-site-audit`. In Claude, open Settings, Connectors, Add custom connector and paste the same URL.
5. Sign in to WordPress and click Allow. Open a new connection window for each additional client.

Use HTTPS and pretty permalinks. Keep 127.0.0.1 and localhost in Allowed callback hosts for the default Codex OAuth flow.

== Frequently Asked Questions ==

= Can the connector change my site? =

The connection grants read-only audit access. Make changes from the WordPress dashboard or your hosting settings.

= Why does the .well-known address return 404? =

Some hosts handle /.well-known/ separately from WordPress. Ask your host to forward the plugin-specific discovery path shown by Test discovery to WordPress while keeping any existing discovery routes. The plugin also serves resource discovery at /nsa-oauth/resource.

= How do I disconnect? =

Under Settings, Nomad Site Audit, click Disconnect next to the app, or Disconnect everything. Unticking Allow connections blocks connector access.

== Screenshots ==

1. Connection settings and discovery test.
2. The WordPress approval screen.
3. Connected apps and the audit activity log.

== Changelog ==

= 1.2.0 =
* Added audit history with pinned baselines and finding comparisons.
* Added the remediation checklist, targeted rechecks, and branded HTML reports.
* Added opt-in weekly email digests and daily page performance monitoring with optional Chrome UX Report data.

= 1.1.1 =
* Added Codex support alongside Claude using MCP OAuth.
* Made all connector tools read-only. Removed remote site changes, custom code and styling, file deletion and database cleanup.
* Serve namespaced OAuth discovery through WordPress without writing files in the site root.
* Validate OAuth and MCP inputs, omit arguments from new log entries and support custom content, theme and upload paths.

= 1.1.0 =
* Prepared for the WordPress.org plugin directory: readme, external services, coding standards
* Renamed the internal prefix to nomadsa. Existing settings, connections, activity and backups are carried across automatically
* Safer database queries, file handling and output escaping
* Redirects back to Claude now use the WordPress safe redirect list

= 1.0.2 =
* New connections accepted only within 15 minutes of an administrator allowing them
* Fix access switches off after 24 hours
* App registrations limited and unused ones removed
* New tools: delete unused themes and leftover files in uploads
* Harmless placeholder and plugin data files in uploads no longer reported as threats

= 1.0.1 =
* Discovery files written to the .well-known folder for hosts that bypass WordPress
* Settings page shows whether the site is ready to connect

= 1.0.0 =
* First release

== Upgrade Notice ==

= 1.1.1 =
Connections now provide audit access only. Custom CSS from older versions stops rendering. The plugin serves discovery at new, namespaced URLs. If your host intercepts those URLs, use Test discovery for the path to forward to WordPress.

= 1.1.0 =
Internal names change in this version. Your settings, connections, activity log and backups are moved across automatically.
