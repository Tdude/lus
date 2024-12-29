# Lus, a reading assessment plugin refactoring project, soon including AI assessments of recorded text passages

## Project Overview

A WordPress plugin for recording and evaluating reading comprehension, being refactored for improved maintainability and extensibility.

## Architecture

- Why "lus"? In Swdish it's LäsUtvecklingsStöd. It's open source, you name it what you want!

### Frontend Structure

- Standardized HTML using data attributes (`data-lus-*`)
- Modular JavaScript architecture
- Consistent UI components throughout the admin and public part
- Event-driven interactions

### Backend Structure

- Standardized AJAX handlers
- Content type trait system
- Unified response format
- Centralized error handling

## Implementation Plan

### Phase 1: Infrastructure (Status: 🔄 In Progress)

- [ ] Core JavaScript Architecture
  - [ ] Event system
  - [ ] UI components
  - [ ] Data handlers
- [ ] PHP Base Classes
  - [ ] Content handler trait
  - [ ] Response formatter
  - [ ] Base AJAX handlers

### Phase 2: Content Types (Status: 📊 Planning)

Each content type follows this implementation sequence:

1. Database Schema
2. PHP Handlers
3. JavaScript Handlers
4. UI Components
5. Testing
6. Documentation

#### Passages Module (Status: 🔜 Next Up)

- [ ] Database schema
- [ ] CRUD operations
- [ ] UI implementation
- [ ] Testing

#### Questions Module (Status: 📋 Planned)

- [ ] Database schema
- [ ] CRUD operations
- [ ] UI implementation
- [ ] Testing

#### Recordings Module (Status: 📋 Planned)

- [ ] Database schema
- [ ] CRUD operations
- [ ] UI implementation
- [ ] Testing

#### Assignments Module (Status: 📋 Planned)

- [ ] Database schema
- [ ] CRUD operations
- [ ] UI implementation
- [ ] Testing

### Phase 3: Integration (Status: 📋 Planned)

- [ ] Cross-module functionality
- [ ] Performance optimization
- [ ] Browser compatibility testing
- [ ] User acceptance testing

## File Structure

```
├── NFO.md
├── README.md
├── admin
│   ├── class-lus-admin.php
│   ├── css
│   │   └── lus-admin.css
│   ├── js
│   │   ├── handlers
│   │   │   ├── lus-chart-handler.js
│   │   │   ├── lus-passages-handler.js
│   │   │   ├── lus-questions-handler.js
│   │   │   ├── lus-recordings-handler.js
│   │   │   └── lus-results-handler.js
│   │   ├── lus-core.js
│   │   ├── lus-handlers.js
│   │   └── lus-ui.js
│   └── partials
│       ├── lus-assignments.php
│       ├── lus-dashboard.php
│       ├── lus-passages.php
│       ├── lus-questions.php
│       ├── lus-recordings.php
│       ├── lus-results.php
│       └── widgets
│           ├── lus-admin-activity.php
│           ├── lus-assessment-modal.php
│           ├── lus-login-message.php
│           ├── lus-pagination.php
│           ├── lus-recent-recordings.php
│           └── lus-statistics.php
├── changelog.txt
├── check.php
├── includes
│   ├── class-lus-activator.php
│   ├── class-lus-assessment-handler.php
│   ├── class-lus-container.php
│   ├── class-lus-database.php
│   ├── class-lus-database0.php
│   ├── class-lus-deactivator.php
│   ├── class-lus-evaluator.php
│   ├── class-lus-events.php
│   ├── class-lus-export-handler.php
│   ├── class-lus-feature-flags.php
│   ├── class-lus-i18n.php
│   ├── class-lus-loader.php
│   ├── class-lus-recorder.php
│   ├── class-lus-statistics.php
│   ├── class-lus.php
│   ├── config
│   │   ├── admin-strings.php
│   │   └── class-lus-constants.php
│   ├── dto
│   │   ├── class-lus-passage-dto.php
│   │   └── class-lus-recording-dto.php
│   ├── factory
│   │   └── class-lus-handler-factory.php
│   ├── lus-login-message.php
│   ├── strategy
│   │   ├── class-lus-levenshtein-strategy.php
│   │   └── interface-lus-evaluation-strategy.php
│   └── value-objects
│       ├── class-lus-difficulty-level.php
│       ├── class-lus-duration.php
│       ├── class-lus-score.php
│       └── class-lus-status.php
├── languages
│   ├── lus-sv_SE.po
│   └── lus.pot
├── lus.php
├── public
│   ├── class-lus-public.php
│   ├── css
│   │   └── lus-public.css
│   ├── js
│   │   └── lus-public.js
│   └── uninstall.php
└── uninstall.php

```

## Technical Documentation

### Data Attributes

Standard data attributes used throughout the plugin:

- `data-lus-action`: Action type (e.g., "edit", "delete")
- `data-lus-type`: Content type (e.g., "passage", "question")
- `data-lus-id`: Item identifier
- `data-lus-form`: Form identifier
- `data-lus-modal`: Modal identifier

### JavaScript Events

Standard events emitted by the system:

- `{type}:edit`: Edit action triggered
- `{type}:delete`: Delete action triggered
- `{type}:saved`: Save completed
- `{type}:error`: Error occurred

### API Endpoints

AJAX endpoints follow the pattern:

- `lus_admin_{type}_{action}`
  Example: `lus_admin_passage_save`

## Progress Log

- 2024-12-18: Initial refactoring plan created
- Using Claude AI to do this but hitting limit within a few prompts in spite of paid plan. Frustrating! The main reason being I have to upload most previous files for Claude context comprehension.
- [Future entries as we progress]

## Future

- Trying to use an open architecture so the public part can be code agnostic. Wordpress being a very popular platform, let's start here. This is getting to be a comprehensive plugin you can make a business with. However, there is so much more in terms of contacts within your country's education system to handle, to reach proper traction. I am hoping this can be a jump start for any company or school system who want to tackle (some) modern kids' challenges in learning how to read and comprehend text.

> If we "save" one in a million, there are still million$ to be saved!

## Testing

- Unit tests for each module
- Integration tests for cross-module functionality
- Browser compatibility testing
- Performance benchmarks

## Contributors

- Claude AI with a very limited account, Tibor Berki

## License

GPL v2 or later
