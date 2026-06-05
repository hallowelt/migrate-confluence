# Coding Rules for Composer Component

The Composer assembles converted WikiText content and resources into a MediaWiki importable XML format.

## 1. Processor Pattern

Composer processors handle building specific parts of the final MediaWiki XML.

### File Naming & Location
- Location: `src/Composer/Processor/{Entity}.php`
- Pattern: Plural entity names (Pages, Files, Comments)
- Examples: `Pages.php`, `Comments.php`, `Files.php`

### Class Convention
- Implements: `IConfluenceComposerProcessor`
- Extends: `ProcessorBase`
- Namespace: `HalloWelt\MigrateConfluence\Composer\Processor`
- Method to implement: `process( Builder $builder, ... ): void`

### Processor Responsibilities
- Read converted data from workspace files
- Read metadata from `WorkspaceDB`
- Build XML elements using `Builder` class
- Add pages, files, or metadata to the MediaWiki XML output

## 2. Processor Methods

### Standard Methods in ProcessorBase
- `__construct()`: Accept `Builder`, `DBComposerDataLookup`, `Workspace`, `Output`, etc.
- `process()`: Main entry point for building XML elements
- `getName()`: Return processor identifier string

### File Naming & Location
- Location: `src/Composer/Processor/{Name}ContentPostProcessor.php`
- Example: `TemplateContentPostProcessor.php`

### Class Convention
- Name: Name of the processed object, e.g. `Page.php`, `Blog.php`
- Namespace: `HalloWelt\MigrateConfluence\Composer\Processor`


### Responsibilities
- Accept page content as WikiText string

## 3. Processor Registration

All processors must be registered in `ConfluenceComposer::buildXML()`:

1. **Create processor instance** with required dependencies:
   - `Builder` instance
   - `DBComposerDataLookup` for data access
   - `Workspace` for file access
   - `Output` for progress reporting
   - `MigrationConfig` for settings

2. **Call processor** in appropriate order:
   - Files: typically first (attachments, images)
   - Pages: main content
   - Comments: page comments
   - Post-processors: applied per-page during processing

### Example Registration Pattern
```php
$processors = [
    new Files(
        $builder, $composerDataLookup, $this->workspace,
        $this->output, $this->dest, $this->migrationConfig,
        $deploymentInfo
    ),
    new Pages(
        $builder, $composerDataLookup, $this->workspace,
        $this->output, $this->dest, $this->migrationConfig,
        $deploymentInfo
    ),
];
```

## 4. Data Lookup Pattern

### DBComposerDataLookup
- Provides convenient access to composed data from database
- Methods like `getPageData()`, `getAttachmentData()`, etc.
- Filters and caches results for performance

## 6. Builder Integration

### Required Data for Builder
- **Pages**: title, content, timestamp, author, page_id
- **Files**: filename, content (binary), description, upload_date

## 7. Progress Reporting

### Output Integration
- Use `$this->output->writeln()` for progress messages
- Report processing status per entity type
- Indicate progress: "Processing 250/1000 pages..."

### Logging
- Use `DBLog` for errors or warnings
- Log skipped items and reasons
- Log final statistics

## 8. Configuration & Deployment Info

### MigrationConfig Usage
- Access namespaces configuration
- Access file extension whitelist
- Access custom replacements or mappings
- Passed to constructor, stored as instance variable

### ComposerDeploymentInfo
- Stores deployment-specific information
- Passed to all processors for consistency
- Used for namespace and prefix mapping

## 9. Adding a New Processor

Steps to add a new Composer Processor:

1. Create `src/Composer/Processor/{Entity}Processor.php`
2. Implement `IConfluenceComposerProcessor` or extend `ProcessorBase`
3. Implement `process()` method:
   - Accept `Builder` and required data sources
   - Read from workspace/database as needed
   - Call appropriate `Builder` methods
4. Register in `ConfluenceComposer::buildXML()` constructor
5. Add appropriate data lookup methods to `DBComposerDataLookup` if needed
6. Test end-to-end XML output
