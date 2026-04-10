# Xophz Golden Keywords

> **Category:** True North · **Version:** 0.0.1

Discover what golden keywords help unlock otherwise missed opportunities.

## Description

**Golden Keywords** is an SEO keyword research and discovery tool for COMPASS. It surfaces high-value, low-competition keyword opportunities that your content can target to unlock organic traffic growth.

### Core Capabilities

- **Keyword Discovery** – Identify untapped keyword opportunities in your niche.
- **Competition Analysis** – Evaluate keyword difficulty and competitive landscape.
- **Content Mapping** – Map discovered keywords to existing or planned content.
- **Performance Tracking** – Monitor keyword rankings over time.

## Requirements

- **Xophz COMPASS** parent plugin (active)
- WordPress 5.8+, PHP 7.4+

## Installation

1. Ensure **Xophz COMPASS** is installed and active.
2. Upload `xophz-compass-golden-keys` to `/wp-content/plugins/`.
3. Activate through the Plugins menu.
4. Access via the My Compass dashboard → **Golden Keywords**.

## PHP Class Map

| Class | File | Purpose |
|---|---|---|
| `Xophz_Compass_Golden_Keys` | `class-xophz-compass-golden-keys.php` | Core plugin hooks |
| `Xophz_Compass_Golden_Keys_API` | `class-xophz-compass-golden-keys-api.php` | REST API for keyword data |

## Frontend Routes

| Route | View | Description |
|---|---|---|
| `/golden-keys` | Dashboard | Keyword discovery, competition analysis, and tracking |

## Changelog

### 0.0.1

- Initial scaffolding with plugin bootstrap, API class, and COMPASS integration
