# Coding Rules for Extractor Component

The Extractor reads data from the analyzed workspace and extracts body contents, attachments, and other resources into the file system.

## 1. Component Structure

### Main Class
- **Class**: `ConfluenceExtractor`
- **Location**: `src/Extractor/ConfluenceExtractor.php`
- **Extends**: `ExtractorBase`
- **Implements**: `IDestinationPathAware`

### Responsibilities
- Read analyzed data from `WorkspaceDB`
- Extract file contents and attachments from the source export
- Organize extracted files in the destination workspace directory
- Create structured output for downstream processing (Converter, Composer)

## 2. Extraction Methods

The Extractor provides methods to extract each data type:

### Method Naming Convention
- Pattern: `extract{EntityType}()`
- Examples: `extractBodyContents()`, `extractAttachments()`
- Access level: `private` or `protected`
- Called from `doExtract()` method

### Common Methods
- `extractBodyContents()`: Extract page/blog post body content
- `extractAttachments()`: Extract file attachments
- `extractImages()`: Extract inline images
- `extractAdditionalAttachments()`: Extract reference attachments

## 3. WorkspaceDB Integration

### Database Access
- `WorkspaceDB` is initialized in `initWorkspaceDB()` method
- Use `getAllData(string $table)` to read analyzed records
- Iterate over records to extract associated files

## 4. File Organization

### Adding New Extraction Type

1. Create `extract{Type}()` method in `ConfluenceExtractor`
2. Query relevant table from `WorkspaceDB`
3. Create destination subdirectory structure
4. Write extracted files using `FilenameResolver` / `FilenameBuilder`
5. Call method from `doExtract()` in appropriate sequence

## 5. Supporting Utilities

### Filename Handling
- **`FilenameResolver`**: Convert Confluence titles/paths to filesystem filenames
- **`FilenameBuilder`**: Construct file paths for organized storage
- **`DBLog`**: Log extraction progress and errors

### Configuration
- **`MigrationConfig`**: Access migration settings
- Initialize via `initMigrationConfig()` method

## 6. Lifecycle & Dependencies

### Initialization Order (in `doExtract()`)
1. `initMigrationConfig()` - Load migration settings
2. `initWorkspaceDB()` - Connect to analyzed data
3. `initDBLog()` - Initialize logging
4. Execute extraction methods in logical order

### Important Notes
- Extractor runs **after** Analyzer, **before** Converter
- Uses data written by Analyzer to WorkspaceDB
- Output files feed into Converter for content transformation
- Should not modify or corrupt source data

## 7. Error Handling

### Logging
- Use `DBLog` to record extraction errors and warnings
- Call `$this->dbLog->addLog()` for significant events
- Log file move/copy failures explicitly

### Robustness
- Verify destination directories exist before writing
- Handle missing files gracefully
- Handle encoding issues in filenames
- Verify file permissions before extracting
