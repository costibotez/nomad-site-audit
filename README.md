<h1 align="center">Nomad Site Audit</h1>

<p align="center">
  <strong>Audit your WordPress site from Codex or Claude.</strong>
</p>

<p align="center">
  A free WordPress plugin that connects your site to Codex or Claude for read-only security, search and site health audits.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/WordPress-6.2%2B-21759b?logo=wordpress&logoColor=white" alt="WordPress 6.2+" />
  <img src="https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php&logoColor=white" alt="PHP 7.4+" />
  <img src="https://img.shields.io/badge/Version-1.2.0-d97706" alt="Version 1.2.0" />
  <img src="https://img.shields.io/badge/Model%20Context%20Protocol-2025--11--25-6d28d9" alt="Model Context Protocol 2025-11-25" />
  <img src="https://img.shields.io/badge/License-GPL--2.0--or--later-0f766e" alt="License GPL-2.0-or-later" />
</p>

<p align="center">
  <a href="#-quick-start-under-5-minutes">Quick start</a> ·
  <a href="#what-the-plugin-includes">Features</a> ·
  <a href="#connection-controls">Connection controls</a> ·
  <a href="#example-prompts">Example prompts</a> ·
  <a href="#audit-history-and-monitoring">Audit dashboard</a> ·
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

Nomad Site Audit gives Codex and Claude access to WordPress audit data so you can investigate site health, SEO and performance from a chat. The client can explain what it finds and point you to the WordPress or hosting controls where you can make changes.

## What the plugin includes

### 🔍 Security audit
- Security checks with findings and next steps for WordPress, plugin and theme updates, inactive plugins and unused themes, PHP version, HTTPS and SSL certificate expiry, debug settings, a public error log, the dashboard code editor, `wp-config.php` permissions, PHP files in uploads, folder browsing, easy to guess usernames, administrator count, open registration, username listing, XML-RPC, browser security headers and version disclosure
- **Malware scan** for obfuscated code, web shells, encoded blobs, remote includes, PHP hidden in images and recently changed PHP files. The scan identifies harmless placeholder and plugin data files in uploads
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

## Connection controls

- An administrator opens a 15-minute window and approves each connection.
- The connector grants read-only audit access. Disconnect an app or disable connector access from **Settings → Nomad Site Audit**.
- OAuth uses PKCE, hashed tokens and refresh token rotation. New activity log entries record tool names and outcomes without storing arguments.
- The connector blocks protected configuration files and common backup formats and redacts recognised secret patterns in file excerpts and logs.

## ⚡ Quick start (under 5 minutes)

1. Upload the plugin under **Plugins → Add New → Upload Plugin** and activate it.
2. Open **Settings → Nomad Site Audit** and copy the connection address ending in `/nsa-mcp`. Click **Test discovery** to verify OAuth discovery.
3. Click **Allow a new connection** just before connecting from your client.
4. Follow the instructions for Codex or Claude. Sign in to WordPress when prompted and click **Allow**. Open a new connection window for each client you add.

### Codex

Add a **Streamable HTTP** MCP server with the URL from WordPress. From the Codex CLI:

```sh
codex mcp add nomad-site-audit --url https://example.com/nsa-mcp
```

If the client prompts for a separate sign-in, run `codex mcp login nomad-site-audit`. Use the URL from your site in place of the example. Keep `127.0.0.1` and `localhost` in **Allowed callback hosts** for the default Codex OAuth flow. [Codex MCP documentation](https://developers.openai.com/codex/mcp)

### Claude

Open **Settings → Connectors → Add custom connector**, name the connector and paste the same URL.

## Example prompts

- *“Run an audit and give me a prioritised list of what to address in WordPress.”*
- *“Why is the homepage slow on mobile?”*
- *“Which plugins or themes have pending updates?”*
- *“Find pages with weak search titles and suggest replacements.”*
- *“Check the core files against WordPress.org checksums.”*

## Hosting notes

- Use **HTTPS** and **pretty permalinks** (any option other than *Plain* under **Settings → Permalinks**).
- The plugin serves OAuth discovery through WordPress. If your host intercepts `/.well-known/`, ask it to forward the plugin-specific path shown by **Test discovery** to WordPress.
- For configured directories outside the WordPress root, file audits support `@content`, `@uploads`, `@plugins`, `@mu-plugins`, `@theme` and `@parent-theme`.
- Upgrading to 1.1.1 stops rendering custom CSS previously stored by the plugin. Older installations may still have discovery files at the old shared paths. Coordinate with your host before changing those paths.

## Audit history and monitoring

Open **Tools → Nomad Site Audit** in WordPress.

1. **Run audit** saves a snapshot of security, database health, site-wide SEO, and SEO checks on the 100 newest published posts and pages. Select an older snapshot and click **Use as baseline** to compare subsequent runs against it. Without a pinned baseline, each audit compares with its predecessor.
2. **Remediation checklist** links each finding to the relevant WordPress screen and gives a recommended next step. Click **Start** to track progress and **Recheck** after making a change. If a check is unavailable or a post falls outside the audit's content sample, the original finding remains unverified.
3. **Download HTML report** exports a standalone, styled file for the selected audit. Set your brand name and accent colour in **Audit settings**. Use **Preview report** and your browser's Print command to save a PDF.
4. **Weekly digest** is opt-in. Enter a recipient and turn it on in **Audit settings**. WordPress emails high-priority findings and regressions discovered in scheduled checks. The screen shows the next run and the outcome of the last delivery attempt.
5. **Performance monitoring** checks up to five public URLs on your site daily. Add the URLs in **Audit settings** or use **Measure now** for an immediate sample. The performance table shows HTTP status, total response time from your WordPress server, and HTML response size.

An optional Chrome UX Report API key adds mobile LCP, INP, and CLS at the 75th percentile for pages with enough traffic to have field data. WordPress sends the chosen public URLs to Google only after you save a key. Missing field data appears separately from an API error. [Chrome UX Report API](https://developer.chrome.com/docs/crux/api)

Monitoring flags response times that grow by at least 25% and 150 ms, and HTML responses that grow by at least 20% and 25 KB. Field-data regressions use the good Core Web Vitals thresholds and minimum changes of 250 ms for LCP, 50 ms for INP, and 0.05 for CLS. [Core Web Vitals thresholds](https://developers.google.com/search/docs/appearance/core-web-vitals)

Audit data stays in the WordPress database. The plugin retains the 52 latest audit snapshots, a pinned baseline, and 90 performance samples. The daily and weekly jobs depend on WordPress Cron, so low-traffic sites may need a system cron to trigger WordPress Cron regularly.

## Development

Requires WordPress 6.2+ and PHP 7.4+. The plugin serves a Streamable HTTP MCP endpoint at `/nsa-mcp`, with OAuth endpoints under `/nsa-oauth` and namespaced discovery under `/.well-known/`. There is no build step.

## Reporting a security issue

Please do not open a public issue for security problems. Email [contact@nomad-developer.co.uk](mailto:contact@nomad-developer.co.uk) with the details and I will reply within 2 working days.

## License

GPL-2.0-or-later.

---

<p align="center">
  Built and maintained by <a href="https://www.nomad-developer.co.uk/">Costin Botez</a>, <a href="https://www.nomad-developer.co.uk/">Nomad Developer</a> · <a href="https://www.instagram.com/costinbotez/">@costinbotez</a><br />
  WordPress development, care plans and performance work for agencies and small businesses.
</p>
