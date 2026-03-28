# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Integration platform between **Shoper** (e-commerce) and **iDosell/IAI** (marketplace) built on **Yii2** (PHP). The app syncs products, customers, orders, categories, tags, and subscribers between the two platforms via a queue-based processing system.

## Common Commands

**Console entry point:**
```bash
php yii <command>/<action>
```

**Key console commands:**
```bash
php yii xml-generator/generate-countries
php yii xml-generator/prepare-queue
php yii xml-generator/generate-categories
php yii xml-generator/generate-tags
```

**Integration scripts (cron-driven):**
```bash
./integration-bash.sh              # Main orchestrator
./integration-bash-orders.sh
./integration-bash-customers.sh
./integration-bash-products.sh
./integration-bash-subscribers.sh
```

**Tests (Codeception):**
```bash
php vendor/bin/codecept run
php vendor/bin/codecept run unit
php vendor/bin/codecept run functional
```

**Web entry:** `web/index.php` | **Test web entry:** `web/index-test.php`
**DB admin:** `/adminer.php`

## Architecture

### Data Flow
1. External systems push data via REST API endpoints (`modules/api/`)
2. Data is enqueued in `xml_feed_queue` table
3. Cron jobs (every 1–10 min) trigger console commands / integration scripts
4. Queue items are processed: XML feeds generated and pushed to iDosell via SOAP

### Queue Status Codes (`xml_feed_queue.status`)
- `0` = Pending
- `1` = Running
- `2` = Completed
- `99` = Error (details stored in `parameters` column)

### Module Responsibilities

| Module | Path | Purpose |
|--------|------|---------|
| `xml_generator` | `modules/xml_generator/` | Core engine — generates XML feeds for all entity types; SOAP calls to iDosell |
| `api` | `modules/api/` | REST API layer — receives data from external systems, manages auth/connections |
| `shoper` | `modules/shoper/` | Shoper platform SDK integration — fetches/maps Shoper entities |
| `IAI` | `modules/IAI/` | iDosell Appstore config management |
| `idosellv3` | `modules/idosellv3/` | iDosell v3 API integration |

### Configuration
- `config/web.php` — main web app config (modules, components, aliases)
- `config/console.php` — console app config
- `config/db.php` — MySQL connection (`samba_shoper` database)
- `config/params.php` — application parameters
- `config/test.php` — test environment overrides

### Key Patterns
- **Models** use Yii2 ActiveRecord (`models/` and per-module `models/`)
- **Authentication** uses OAuth2 + JWT (`lcobucci/jwt 3.3`, `steverhoades/oauth2-openid-connect-client`)
- **Shoper SDK** via `dreamcommerce/shop-appstore-lib`
- Integration state per user tracked via `user` and `accesstokens` tables

### Deployment
- Docker: `docker-compose.yml` (PHP 7.4 + Apache)
- Vagrant: `Vagrantfile` (Ubuntu 18.04)
- Cron schedule: countries daily, queue prep nightly, integrations every 10 min
