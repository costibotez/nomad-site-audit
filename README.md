<h1 align="center">Nomad Site Audit</h1>

<p align="center">
  <strong>Ask Claude to audit your WordPress site. Approve every fix it suggests.</strong>
</p>

<p align="center">
  A free WordPress plugin that connects a site to Claude as a custom connector,<br />
  so Claude can run security, search and health audits directly on the live site<br />
  and apply the fixes you approve, each one previewed first, backed up and logged.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/WordPress-6.2%2B-21759b?logo=wordpress&logoColor=white" alt="WordPress 6.2+" />
  <img src="https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php&logoColor=white" alt="PHP 7.4+" />
  <img src="https://img.shields.io/badge/Version-1.0.2-d97706" alt="Version 1.0.2" />
  <img src="https://img.shields.io/badge/Model%20Context%20Protocol-2025--11--25-6d28d9" alt="Model Context Protocol 2025-11-25" />
  <img src="https://img.shields.io/badge/License-GPL--2.0--or--later-0f766e" alt="License GPL-2.0-or-later" />
</p>

<p align="center">
  <a href="#-quick-start-under-5-minutes">Quick start</a> ·
  <a href="#what-the-plugin-includes">Features</a> ·
  <a href="#-safe-by-design">Safety</a> ·
  <a href="#what-to-ask-claude">Example prompts</a> ·
  <a href="#roadmap">Roadmap</a> ·
  <a href="#development">Development</a>
</p>

<p align="center">
  Author: <a href="https://www.nomad-developer.co.uk/">Costin Botez</a> (<a href="https://www.instagram.com/costinbotez/">@costinbotez</a>) ·
  <a href="https://www.nomad-developer.co.uk/">Portfolio</a> ·
  <a href="https://www.instagram.com/costinbotez/">Instagram</a> ·
  <a href="https://github.com/costibotez">GitHub</a>
</p>

---

## Why Nomad Site Audit exists

Looking after WordPress sites means the same routine every month: log in, check updates, run a security scan, open PageSpeed, dig through Search Console, then write it all up for the client. Claude can do most of that reasoning, but until now it could only see what you pasted or screenshotted.

Nomad Site Audit gives Claude a safe, direct line into the site:

- *"Run a full audit on this site"* becomes about 30 security checks, a malware scan, a search optimisation review and a page speed diagnosis, explained in plain language.
- *"The homepage is slow on phones, fix it"* becomes Claude finding the loading screen and the late hero image, previewing a fix, waiting for your approval, applying it, confirming the homepage still works and showing you the before and after.

It was built during real client maintenance work on a live care plan site.

## What the plugin includes

### 🔍 Security audit
- About 30 checks, each returned as pass, warning or fail with a plain explanation and the tool that fixes it: WordPress, plugin and theme updates, inactive plugins and unused themes, PHP version, HTTPS and SSL certificate expiry, debug settings, a public error log, the dashboard code editor, `wp-config.php` permissions, security keys, PHP files in uploads, folder browsing, easy to guess usernames, administrator count, open registration, username listing, XML-RPC, browser security headers and version disclosure
- **Malware scan** for obfuscated code, web shells, encoded blobs, remote includes, PHP hidden in images and recently changed PHP files. Harmless placeholder and plugin data files in uploads are recognised and not reported as threats
- **File verification** against official WordPress.org checksums, for WordPress itself and for every plugin hosted on WordPress.org: modified, missing and unexpected files
- Optional **known vulnerability checks** for plugins with a free WPScan token

### 📈 Search and speed
- **Search optimisation audit**: indexing, sitemap, robots.txt, permalinks and images without alt text, plus a per page review of search titles, meta descriptions, noindex, word count, main headings, internal links and duplicates. Works with Yoast SEO and Rank Math
- **Page check**: loads any page like a visitor and reports response time, size, title, description, canonical, robots, headings, images, structured data, render blocking scripts, loading screens, mixed content and response headers
- **Broken link finder** for links and images in published content, including Elementor pages

### 🩺 Site health and code review
- Site overview: versions, server, theme, plugins, pending updates, users and the security, search, cache, backup, page builder and form plugins in use
- Database health: size, autoloaded options, revisions, drafts, trash, spam and expired temporary data
- Scheduled tasks with overdue detection, the error log with secrets removed, users with partly hidden email addresses
- Read only file browsing, file reading and code search for reviewing themes and plugins

### 🛠️ Fixes you approve
- Update plugins, themes and WordPress, with a homepage check after every update
- Activate, deactivate or delete plugins; delete unused themes; delete leftover files in uploads
- Security hardening: switch off XML-RPC and the dashboard code editor, hide the WordPress version, stop username listing, remove `readme.html` and `license.txt`
- Browser security headers with a safe recommended preset
- Search titles, meta descriptions and noindex through Yoast SEO or Rank Math; image alt text in bulk
- Find and replace in page content, including Elementor data
- Edit child theme and must-use plugin files, with a PHP syntax check before saving
- Custom CSS, selected site settings, database clean up and cache clearing (WordPress, SiteGround, WP Rocket, LiteSpeed, W3 Total Cache, WP Super Cache, Autoptimize and Elementor)
- **Undo**: every change to files, titles, alt text, content, settings and CSS is backed up and can be restored

### 🔒 Safe by design
- **Preview, then confirm.** Every fix tool returns a preview first and changes nothing. Claude shows it to you and only applies it after you approve that exact change
- **Connection window.** New connections are accepted only within 15 minutes of an administrator clicking *Allow a new connection* and only once per click, so a link someone sends you cannot connect their Claude to your site
- **Administrators only.** Only an administrator can approve a connection and only apps on the allowed list (Claude by default) can receive one
- **Fixes expire.** Fix access switches itself off 24 hours after it is turned on, so a forgotten switch never stays open
- **Secrets stay on the site.** `wp-config.php`, `.env`, keys, database dumps and backup archives can never be read, secrets are removed from anything that is read and access tokens are stored as hashes
- **Automatic rollback.** A code edit that breaks the homepage is undone straight away
- **Full activity log** of every audit, preview and applied fix, with one click disconnect and an instant off switch
- Standard OAuth 2.1 with PKCE, dynamic client registration (limited to 10 an hour) and refresh token rotation. No third party service sits in between: Claude talks to your site and your site answers

## ⚡ Quick start (under 5 minutes)

1. Download the latest release zip and upload it under **Plugins → Add New → Upload Plugin**, then activate it.
2. Open **Settings → Nomad Site Audit** and check for the green *Ready to connect* tick.
3. Tick **Allow Claude to apply fixes for the next 24 hours** if you want fixes. Leave it unticked for audit only access.
4. Click **Allow a new connection**.
5. In Claude, open **Settings → Connectors → Add custom connector**, give it the site's name and paste the connection address from the settings page (it ends in `/nsa-mcp`).
6. Click **Connect**, log in to the site when asked and click **Allow**.

Each site becomes its own connector in Claude. Start a new chat and ask for an audit.

## What to ask Claude

- *"Run a full audit and give me a prioritised list."*
- *"Why is the homepage slow on mobile? Preview a fix."*
- *"Update all plugins, then check the homepage still works."*
- *"Find pages with missing or too long meta descriptions and suggest better ones."*
- *"Scan for malware and check the core files against WordPress.org."*
- *"What did you change on this site last month?"* (reads the activity log)

## Hosting notes

- **Pretty permalinks are required.** Any option other than *Plain* under **Settings → Permalinks** works.
- **SiteGround and other nginx hosts** answer `/.well-known/` themselves instead of passing it to WordPress. The plugin writes its two discovery files into the `.well-known` folder automatically and the settings page tests the address. If the test fails, flush the host cache and reload.
- **Updates through Claude need direct file access**, the normal setup on SiteGround and most managed hosts. Sites that ask for FTP details when updating can still be audited.

## Roadmap

- `readme.txt` and a WordPress.org listing
- Image optimisation: convert large hero images to WebP and point the page at the new file
- Targeted Elementor edits (change one heading level or one widget setting safely)
- Agency view: one connector covering many sites, building on the [Aftercare](https://github.com/costibotez/aftercare) change ledger
- Scheduled audit reports emailed to the site owner

## Development

- WordPress 6.2+, PHP 7.4+. Plain PHP classes, no build step, no Composer dependencies.
- **How it works:** the plugin is a small Model Context Protocol server (Streamable HTTP transport, JSON responses) with its own OAuth 2.1 authorisation server. Requests to `/nsa-mcp`, `/nsa-oauth/*` and `/.well-known/oauth-*` are answered on `init` before WordPress builds a page.
- **Tests:** the plugin is tested end to end against a real WordPress install on SQLite, through the same flow Claude uses: registration, login, consent, token exchange, every audit tool, previews and confirmed fixes, rollback, refresh token rotation, the connection window, fix expiry and the safety refusals (secret files, path traversal, audit only connections and invalid tokens).

```
nomad-site-audit.php          Bootstrap, constants and hooks
uninstall.php                 Removes tables, options and discovery files
includes/
  class-nsa-http.php          Routing for the connector addresses, request helpers
  class-nsa-oauth.php         Authorisation server, consent screen, tokens
  class-nsa-wellknown.php     Discovery files for hosts that bypass WordPress
  class-nsa-mcp.php           Model Context Protocol endpoint
  class-nsa-tools.php         Tool registry, path guards, secret redaction
  class-nsa-audit-tools.php   Read only tools
  class-nsa-fix-tools.php     Fix tools (preview, confirm, backup)
  class-nsa-runtime.php       Applies stored fixes: headers, hardening, custom CSS
  class-nsa-admin.php         Settings, connected apps and activity screen
  class-nsa-settings.php      Settings, connection window and fix window
  class-nsa-db.php            Tables for apps, tokens, activity log and backups
```

### Adding your own tools

Any plugin or must-use plugin can add tools to the connector:

```php
add_action( 'nsa_register_tools', function () {
	NSA_Tools::register( 'woocommerce_orders_today', array(
		'title'       => 'Orders today',
		'description' => 'Number and total of WooCommerce orders placed today.',
		'callback'    => function ( $args ) {
			$orders = wc_get_orders( array( 'date_created' => '>' . strtotime( 'today' ), 'limit' => -1 ) );
			return array(
				'summary' => count( $orders ) . ' orders today',
				'total'   => array_sum( array_map( function ( $o ) { return (float) $o->get_total(); }, $orders ) ),
			);
		},
	) );
} );
```

Set `'kind' => 'fix'` to make a tool that changes the site. Its callback receives `( $args, $confirm )` and must return a preview without changing anything when `$confirm` is false.

| Hook | Type | Purpose |
|---|---|---|
| `nsa_register_tools` | action | Register extra tools with `NSA_Tools::register()` |

## Reporting a security issue

Please do not open a public issue for security problems. Email [contact@nomad-developer.co.uk](mailto:contact@nomad-developer.co.uk) with the details and I will reply within 2 working days.

## License

GPL-2.0-or-later.

---

<p align="center">
  Built and maintained by <a href="https://www.nomad-developer.co.uk/">Costin Botez</a>, <a href="https://www.nomad-developer.co.uk/">Nomad Developer</a> · <a href="https://www.instagram.com/costinbotez/">@costinbotez</a><br />
  WordPress development, care plans and performance work for agencies and small businesses.
</p>
