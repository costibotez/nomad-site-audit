=== Nomad Site Audit ===
Contributors: costibotez
Tags: security, seo, audit, maintenance, ai
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect your site to Claude to run security, search and health audits and apply the fixes you approve, each previewed first and logged.

== Description ==

Nomad Site Audit connects a WordPress site to Claude as a custom connector. Claude can then check the live site directly, explain what it finds in plain language and, when you allow it, apply fixes. Every fix is previewed first and only applied after you approve that exact change.

It was built for freelancers, agencies and site owners who look after WordPress sites month after month and want the routine checks done faster and explained better.

= Security audit =

* About 30 checks, each returned as pass, warning or fail with a plain explanation: updates, inactive plugins and unused themes, PHP version, HTTPS and SSL certificate expiry, debug settings, a public error log, the dashboard code editor, wp-config.php permissions, security keys, PHP files in uploads, folder browsing, easy to guess usernames, administrator count, open registration, username listing, XML-RPC, browser security headers and version disclosure
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

= Fixes you approve =

* Plugin, theme and WordPress updates, with a homepage check after every update
* Activate, deactivate or delete plugins, delete unused themes and remove leftover files in uploads
* Security hardening: switch off XML-RPC and the dashboard code editor, hide the WordPress version, stop username listing and remove readme.html and license.txt
* Browser security headers with a safe recommended preset
* Search titles, meta descriptions and noindex through Yoast SEO or Rank Math plus image alt text in bulk
* Find and replace in page content, including Elementor data
* Edits to child theme and must-use plugin files, with a PHP syntax check before saving and automatic rollback if the homepage breaks
* Custom CSS, selected site settings, database clean up, cache clearing and undo for every backed up change

= Safe by design =

* Every fix tool returns a preview first and changes nothing until it is confirmed
* New connections are accepted only within 15 minutes of an administrator clicking Allow a new connection and only once per click
* Only administrators can approve a connection and only apps on the allowed list (Claude by default) can receive one
* Fix access switches itself off 24 hours after it is turned on
* wp-config.php, .env files, keys, database dumps and backup archives can never be read and secrets are removed from anything that is read
* Access tokens are stored as hashes, registrations are limited to 10 an hour and every audit, preview and applied fix is logged
* Standard OAuth 2.1 with PKCE and refresh token rotation. No service of ours sits in between: Claude talks to your site and your site answers

Claude is a trademark of Anthropic, PBC. This plugin is an independent project and is not made, endorsed or supported by Anthropic.

== External services ==

This plugin connects to the services below. It does not send any data to the plugin author.

= Claude (Anthropic) =

When you connect the site in Claude, Claude's servers send requests to this site's connector address and receive the results of the audits and fixes you ask for: site settings, plugin and theme lists, page and post titles and descriptions, file contents you ask Claude to review (with secrets removed) and the outcome of changes. Nothing is sent until an administrator approves the connection and you can disconnect at any time under Settings, Nomad Site Audit.

* Terms of service: https://www.anthropic.com/legal/consumer-terms
* Privacy policy: https://www.anthropic.com/legal/privacy

= WordPress.org =

Used only when an audit needs it. The plugin requests plugin details from api.wordpress.org (plugin slugs, to check whether a plugin is up to date, abandoned or closed), official core checksums from api.wordpress.org (WordPress version and language) and official plugin checksums from downloads.wordpress.org (plugin slug and version).

* Terms of use and privacy policy: https://wordpress.org/about/privacy/

= WPScan (optional) =

Only used if you enter a WPScan API token in the settings. The plugin then sends the slugs of installed plugins and your token to wpscan.com to look up known vulnerabilities. The free WPScan API is limited to 25 requests a day and to non-commercial use; commercial use needs a WPScan licence.

* Terms of service: https://wpscan.com/terms
* Privacy policy: https://automattic.com/privacy/

== Installation ==

1. Install the plugin from Plugins, Add New, or upload the zip under Plugins, Add New, Upload Plugin, then activate it.
2. Open Settings, Nomad Site Audit and check for the green Ready to connect tick.
3. Tick Allow Claude to apply fixes for the next 24 hours if you want fixes. Leave it unticked for audit only access.
4. Click Allow a new connection.
5. In Claude, open Settings, Connectors, Add custom connector, give it the site's name and paste the connection address from the settings page.
6. Click Connect, log in to the site when asked and click Allow.

Pretty permalinks are required (any option other than Plain under Settings, Permalinks).

== Frequently Asked Questions ==

= Do I need a Claude account? =

Yes. You add the site as a custom connector in Claude.

= Can Claude change my site without asking? =

No. Every fix tool returns a preview first. Claude has to show it to you and call the tool again with your approval before anything changes. Fixes are also switched off by default and switch themselves off 24 hours after you turn them on.

= What can never be read? =

wp-config.php, .env files, private keys, database dumps, backup archives and version control folders. Anything that looks like a password, key or token is removed from files and logs before they leave the site.

= Can I undo a change? =

Changes to files, search titles, alt text, page content, settings and custom CSS are backed up and can be restored. Updates, deleted plugins and themes, deleted files and database clean ups cannot be undone through the connector, so keep regular site backups.

= My host shows a 404 for the .well-known address =

Some hosts, including SiteGround, answer /.well-known/ themselves instead of passing it to WordPress. The plugin writes its two discovery files into the .well-known folder for this reason. If the settings page still shows a warning, flush the host cache and reload.

= Updates fail with a message about FTP details =

Updates through Claude need direct file access, which is the normal setup on most managed hosts. Sites that ask for FTP details when updating can still be audited; run updates from the dashboard instead.

= How do I disconnect? =

Under Settings, Nomad Site Audit, click Disconnect next to the app, or Disconnect everything. Unticking Allow connections blocks all access straight away.

= Can I add my own tools? =

Yes. Hook into nomadsa_register_tools and call NomadSA_Tools::register(). See the project on GitHub for an example.

== Screenshots ==

1. Settings: connection address, connection window, fix switch and allowed apps.
2. The approval screen shown before Claude can connect.
3. Connected apps and the activity log of audits, previews and applied fixes.

== Changelog ==

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

= 1.1.0 =
Internal names change in this version. Your settings, connections, activity log and backups are moved across automatically.
