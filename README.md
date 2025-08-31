# Security Audit & Fixer — WordPress Plugin

Security Audit & Fixer is a WordPress admin plugin that scans your site for common security misconfigurations and exposures, then offers one‑click fixes. It focuses on safe, reversible hardening and clear guidance when server-level changes are required.

## Key Features

- Vulnerability scans
  - Exposed WordPress version
    - Generator meta tag
    - readme.html
    - wp-links-opml.php generator
  - Exposed sensitive files
    - wp-config.php
    - license.txt
    - readme.html
    - wp-admin/install.php
    - wp-admin/upgrade.php
    - wp-content/debug.log
    - phpinfo.php
    - .env
  - Weak settings (examples)
    - Default “admin” username
    - Default database table prefix wp_
- One‑click fixes
  - Remove generator meta, sanitize feed generators
  - Delete or block exposed files
  - Rotate/disable debug.log
  - Rename default “admin” user (modal prompt)
  - Change DB table prefix (modal prompt, with validation)
- Server-specific handling
  - Auto-write .htaccess on Apache for file blocking
  - Nginx/OpenResty advisory modal with exact config snippets
  - Optional server type override setting to avoid misdetection
- Hygiene and safety
  - Nonce and capability checks
  - Stores scan results/options; cleans up on uninstall
  - Conservative fixes with clear error reporting

## Installation

1. Place the plugin folder into `wp-content/plugins/security-audit-fixer` (or your chosen slug).
2. Ensure the main plugin file is `security-audit-fixer.php` with a standard plugin header.
3. Activate via WordPress Admin → Plugins.

Requirements:
- WordPress 5.8+
- PHP 7.4+ (PHP 8.x recommended)

## How It Works

The plugin registers an admin menu with:
- Scan: Run a full site scan and view issues.
- Fixes: Apply one‑click fixes (some fixes open a modal for input or server guidance).
- Settings: Configure advanced behavior (e.g., server type override).

Typical flow:
1. Go to Security Audit & Fixer → Scan → “Run Scan.”
2. Review findings; each issue shows severity, details, and a Fix button (when available).
3. Click “Apply Fix” or use “Fix All Exposed Files.”
4. Re-run Scan to verify.

## Scans and Fixes

### Exposed WordPress Version

- Checks:
  - HTML `<meta name="generator">`
  - `readme.html`
  - `wp-links-opml.php` generator tag
- Fixes:
  - Remove `wp_head` generator tag
  - Filter `the_generator` / `get_the_generator` to blank
  - Remove/block `readme.html`
  - Sanitize OPML generator output (fallback buffer)
- Notes:
  - Clear caches/CDN after applying.

### Exposed Sensitive Files

- Targets:
  - `wp-config.php`
  - `license.txt`
  - `readme.html`
  - `wp-admin/install.php`
  - `wp-admin/upgrade.php`
  - `wp-content/debug.log`
  - `phpinfo.php`
  - `.env`
- Fixes:
  - Delete/rename benign files (license/readme/install/upgrade/phpinfo/.env)
  - Rotate or truncate `debug.log`; set `WP_DEBUG` / `WP_DEBUG_LOG` to false in `wp-config.php` when applicable
  - Block `wp-config.php` via `.htaccess` (Apache) or show Nginx snippet via modal
- Detection:
  - Scanner uses HTTP HEAD/GET and flags 200/206 (and 403 for some, like debug.log) as exposed.

### Weak Settings

- Default “admin” username
  - Fix via modal prompting for a new valid username (not “admin,” not taken, allowed chars)
- Default DB table prefix “wp_”
  - Scan flags “wp_”
  - Fix via modal prompting for a new prefix with validation:
    - Starts with a letter
    - Contains only letters/numbers/underscores
    - Ends with an underscore
  - Renames tables and updates `wp-config.php`
  - Back up DB and `wp-config.php` first

## Server-Type Awareness (Apache vs Nginx)

Some fixes require server rules. The plugin detects your server:

- Apache: Auto-writes `.htaccess` rules (e.g., protect `wp-config.php`)
- Nginx/OpenResty/Unknown: Shows a modal with exact config to paste into server config

You can override detection:
- Settings → Server Type → choose Apache or Nginx to prevent misdetection.

Example Nginx snippet for `wp-config.php`
```
    location = /wp-config.php {
        deny all;
    }
```

## UI Components

- Apply Fix: For any issue with a `fix_key`
- Fix All Exposed Files: Bulk-applies safe fixes; if server rules are needed on Nginx, a modal opens with instructions
- Modals:
  - Rename admin username
  - Change DB table prefix
  - Server advisory (Nginx/Apache snippets)

## Configuration

- Server Type override: Auto-detect (default), Apache, Nginx/OpenResty
- Optional toggles can be added on request (e.g., strip `?ver=` from asset URLs; off by default to preserve cache-busting)

## Safety and Best Practices

- Back up your database and `wp-config.php` before high-impact changes (table prefix).
- Avoid enabling `WP_DEBUG_LOG` on production; the plugin can disable it.
- `.htaccess` only affects Apache; Nginx users must add the provided rules manually.
- Obfuscation reduces fingerprints but isn’t a substitute for updates, least-privilege, WAF, backups, and monitoring.

## File Structure (abridged)

security-audit-fixer/
├─ security-audit-fixer.php # plugin bootstrap
├─ uninstall.php # cleanup logic
├─ includes/
│ ├─ class-saf-admin.php # admin pages, form handlers
│ ├─ class-saf-scanner.php # scanners
│ ├─ class-saf-fixer.php # applies fixes
│ ├─ helpers.php # helpers (server detection, options, capabilities)
│ └─ class-saf-logger.php # optional logging
├─ admin/views/
│ ├─ scan.php # scan results and actions
│ ├─ fixes.php # fixer UI + modals (incl. server advisory)
│ └─ settings.php # settings (server override, etc.)
└─ assets/
├─ js/admin.js # admin interactivity & modal handling
└─ css/admin.css # styles

## Hooks Used (examples)

- Actions:
  - `init`: remove version/meta links, set output buffers for OPML if needed
  - `admin_post_saf_apply_fix`: process Apply Fix submissions
  - `template_redirect` / `shutdown`: optional output buffers for special endpoints
- Filters:
  - `the_generator`, `get_the_generator`: blank/neutral generator values
  - `script_loader_src`, `style_loader_src`: optional version stripping (off by default)

## Uninstall

On plugin deletion (not just deactivation), `uninstall.php`:
- Removes plugin options and custom data created by the plugin
- Does not undo structural changes (e.g., new table prefix)

## Troubleshooting

- Modal not showing for server advice:
  - Ensure `assets/js/admin.js` is enqueued on Fixes page
  - Verify redirect includes `?saf_server_advice=...`
  - Confirm modal HTML exists in `admin/views/fixes.php`
- On Nginx but `.htaccess` was created:
  - Settings → Server Type = Nginx
  - Use strict detection helpers
  - Remove the unintended `.htaccess` (harmless on Nginx)
- Version still detected:
  - Clear caches/CDN
  - Confirm `wp-links-opml.php` generator is sanitized
  - Ensure `readme.html` is removed or blocked

## Roadmap

- Directory exposure checks (`.git/HEAD`, `vendor/`, backups)
- Optional path obfuscation for `wp-content` / `wp-includes` (with server rewrites)
- Granular role/capability management
- REST API exposure checks

## Contributing

Issues and PRs are welcome. Please include:
- Environment (WP version, PHP version, Apache/Nginx)
- Repro steps, expected vs actual behavior
- Avoid risky auto-fixes; prefer explicit, reversible changes

## License

MIT License (see LICENSE if present).

## Disclaimer

This plugin provides best-effort hardening and guidance. It does not guarantee absolute security. Keep WordPress core, themes, and plugins updated, and practice defense-in-depth: WAF, backups, monitoring, and least-privilege access.