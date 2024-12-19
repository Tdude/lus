# Reading Assessment Plugin Refactoring

## Project Overview

A WordPress plugin for recording and evaluating reading comprehension, being refactored for improved maintainability and extensibility.

## Architecture

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
reading-assessment/
├── admin/
│   ├── js/
│   │   ├── lus-core.js
│   │   ├── lus-handlers.js
│   │   └── lus-ui.js
│   ├── css/
│   │   └── lus-admin.css
│   └── class/
│       ├── lus-base-handler.php
│       ├── lus-content-handler.php
│       └── lus-response-formatter.php
├── includes/
│   └── [core plugin files]
└── public/
    └── [public-facing files]
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

- YYYY-MM-DD: Initial refactoring plan created
- [Future entries as we progress]

## Reference

Old implementation files are maintained in `/reference` for documentation purposes.

## Testing

- Unit tests for each module
- Integration tests for cross-module functionality
- Browser compatibility testing
- Performance benchmarks

## Contributors

- [List of contributors]

## License

GPL v2 or later
