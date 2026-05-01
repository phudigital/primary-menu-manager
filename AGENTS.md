# Agent Guide - Primary Menu Manager

This file is for AI coding agents working on this plugin later.

## Product Boundary

Primary Menu Manager only controls:

- conditional nav menu item replacement
- conditional header logo URL replacement
- conditional header logo link replacement
- admin settings for those rules

Do not move theme layout, mobile header behavior, CSS animations, or page-builder section logic into this plugin unless the user explicitly asks for that scope.

## Main Files

- `primary-menu-manager.php`: WordPress plugin entrypoint, admin UI, runtime hooks, sanitization, and helper functions.
- `README.md`: user/operator documentation.
- `assets/primary-menu-manager-admin.svg`: README/admin preview illustration.
- `tests/pmm-logo-rule-test.php`: focused PHP CLI regression test for logo rule behavior.
- `dist/primary-menu-manager.zip`: generated release package.

## Extension Points

Prefer these hooks over editing runtime logic directly:

- `pmm_rules`
- `pmm_sanitized_rule`
- `pmm_rule_matches_current_request`
- `pmm_matching_menu_rule`
- `pmm_filtered_menu_items`
- `pmm_menu_item_object`
- `pmm_current_logo_config`

When adding a new rule field:

1. Add the field to `pmm_blank_rule()`.
2. Sanitize it in `pmm_sanitize_rule()`.
3. Render it in `pmm_render_rule_card()` only if it belongs in the admin UI.
4. Use a hook or helper in runtime matching.
5. Add or update a focused CLI test.

## Validation

Run these before committing:

```bash
php -l primary-menu-manager.php
php tests/pmm-logo-rule-test.php
```

## Packaging

Build the WordPress upload package with a top-level folder:

```bash
mkdir -p dist
zip -r dist/primary-menu-manager.zip primary-menu-manager -x "primary-menu-manager/.git/*" "primary-menu-manager/dist/*" "primary-menu-manager/.DS_Store"
```

If running from inside the plugin directory, package from the parent directory so the zip contains `primary-menu-manager/` as the top-level folder.

## Release Notes

For each release, report:

- version
- commit hash
- package path
- validation commands
- GitHub release URL, if published
