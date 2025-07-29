# Changelog

All notable changes to AI Content Automator will be documented in this file.

## [0.3.0] - 2024-12-19

### Added - Phase 2-3 Features
- **Structured Data Injection**: Automatic JSON-LD schema markup for generated posts
  - Article schema with author, dates, and featured images
  - FAQ schema extraction from H3/H4 patterns and div.faq-q/faq-a elements
  - Hash-based caching to prevent redundant schema generation
  - Rank Math integration with graceful fallback to wp_head
  - Admin settings for enabling/disabling Article and FAQ schemas

- **Robots Protection**: Temporary blocking of duplicate/low-value content
  - 48-hour X-Robots-Tag: noindex,nofollow headers for blocked content
  - Query variable-based blocking system (aica_blocked=1)
  - Automatic cleanup of expired blocks via hourly cron
  - Batch rewrite rules updates for performance
  - Optional Google Search Console indexing API integration

- **Competitor Audit API**: Comprehensive competitor analysis tools
  - REST API endpoint `/wp-json/aica/v1/audit?url=` with rate limiting
  - SEO metrics extraction (title, word count, heading structure, meta description)
  - Image alt text percentage analysis
  - Core Web Vitals and accessibility issue detection (stubs)
  - 24-hour caching with 2-second rate limiting
  - Admin interface with vanilla JavaScript (no build step required)

- **Admin Interface Enhancements**:
  - New "Competitor Audit" tab with URL analysis functionality
  - Structured data settings in Domain Profile tab
  - Apply keywords and copy structure buttons for competitor insights

### Technical Improvements
- Added `aica_content_published` action hook fired after successful post creation
- Integrated Phase 2-3 cleanup tasks into existing hourly cron scheduler
- Added opis/json-schema dependency for schema validation
- Updated plugin version to 0.3.0
- Comprehensive integration tests for all Phase 2-3 features

### Dependencies
- Added: `opis/json-schema: ^2.4` for JSON-LD schema validation

## [0.2.0] - Previous Release
- Core AI content generation functionality
- Strategy management and scheduling
- Domain profile building
- Embedding-based duplicate detection
- Image generation integration
- WordPress cron scheduling

## [0.1.0] - Initial Release
- Basic plugin structure and foundation
