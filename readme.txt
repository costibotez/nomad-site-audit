=== Nomad Site Audit ===
Contributors: costibotez
Tags: security, seo, audit, maintenance, ai
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Audit WordPress security, SEO, and site health. Compare findings over time and optionally connect Codex or Claude.

== Description ==

Nomad Site Audit brings security, SEO, and site health checks into your WordPress dashboard. Capture a baseline before maintenance, compare findings after updates, and use the remediation checklist to verify changes. Share the results with a client or team in a branded report.

Run audits and set up monitoring directly in WordPress. To explore your site's audit data from a chat, optionally connect Codex or Claude through the Model Context Protocol (MCP). Each connection requires approval from a WordPress administrator and grants read-only access to the audit tools.

= Audit your site =

* **Security** checks cover software updates, HTTPS, certificate expiry, debug settings, public file exposure, and administrator accounts. Scan for signs of malware and verify WordPress core and supported plugins against official checksums. Known vulnerability lookups are available with an optional WPScan API token.
* **SEO** checks cover search visibility, sitemaps, robots.txt, titles, meta descriptions, headings, image alt text, and broken links. The audit reads metadata from Yoast SEO and Rank Math when available.
* **Site health** checks cover installed plugins and themes, scheduled tasks, and database metrics such as autoloaded options and revisions. The read-only connector can also inspect supported files and error logs, with recognised secret patterns redacted.

= Track findings and performance =

* Save audit snapshots and pin a baseline to see which findings are new, recurring, worsened, resolved, or unverified.
* Use the remediation checklist to open the relevant WordPress screen, track progress, and recheck a finding after making changes.
* Export a branded HTML report or print it to PDF from the report preview.
* Enable weekly email digests for high-priority findings and regressions.
* Monitor up to five public pages daily for response time, HTML size, and HTTP status. Add a Chrome UX Report API key to include mobile Core Web Vitals where field data is available.

Open **Tools → Nomad Site Audit** to run an audit and configure monitoring. A snapshot covers security, database health, site-wide SEO, and SEO checks on up to 100 of the newest published posts and pages. Scheduled audits and monitoring use WordPress Cron.

= Control connector access =

* Open a 15-minute window to approve a new connection in WordPress.
* Review activity, disconnect individual apps, or disable connector access from **Settings → Nomad Site Audit**.
* OAuth uses PKCE, hashed tokens, and refresh token rotation. File inspection blocks common sensitive configuration and backup paths, and the activity log records tool names and outcomes.

Nomad Site Audit is developed and maintained by Costin Botez. For author inquiries, email contact@nomad-developer.co.uk.

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

1. In WordPress, open **Plugins → Add New**, search for **Nomad Site Audit**, and install and activate it.
2. Open **Tools → Nomad Site Audit** and select **Run audit**. Use **Audit settings** to configure reports, digests, and performance monitoring.

= Optional setup for Codex or Claude =

1. Open **Settings → Nomad Site Audit**. Copy the connector URL and use **Test discovery** to check that your host routes the OAuth metadata URL to WordPress.
2. Select **Allow a new connection** before starting sign-in from your client.
3. In Codex, add a Streamable HTTP MCP server with the copied URL. From the CLI, run `codex mcp add nomad-site-audit --url https://example.com/nsa-mcp` using your site's URL. If prompted, run `codex mcp login nomad-site-audit`. In Claude, go to **Settings → Connectors → Add custom connector** and paste your connector URL.
4. Sign in to WordPress and select **Allow**. Open a new connection window for each additional client.

Use HTTPS on public sites and enable pretty permalinks for the connector. Keep `127.0.0.1` and `localhost` in **Allowed callback hosts** for the default Codex OAuth flow.

== Frequently Asked Questions ==

= Do I need Codex or Claude to run an audit? =

You can run audits, review snapshots, recheck findings, download reports, and monitor performance from your WordPress dashboard. The connector is optional.

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
