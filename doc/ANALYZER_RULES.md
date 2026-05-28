# Coding Rules for migrate-confluence-rules

## 1. Analyzer Processor Pattern

Each XML entity processed by the Analyzer requires:

### File Naming
- Location: `src/Analyzer/Processor/`
- Pattern: `{EntityName}.php` (singular, PascalCase)
- Examples: `Page.php`, `BlogPost.php`, `Users.php`, `Comments.php`

### Class Convention
- Implements: `IAnalyzerProcessor`
- Extends: `ProcessorBase`
- Name: `{EntityName}` class in namespace `HalloWelt\MigrateConfluence\Analyzer\Processor`

### Database Table Requirement
Each processor must have corresponding table(s) in WorkspaceDB:
- Primary table: `snake_case` plural form (e.g., `pages`, `blog_posts`)
- Meta/auxiliary tables: `{primary}_meta`, `{primary}_additional`, etc.
- Registration: Must be added to `WorkspaceDB::createTables()` and `$allowedTables` whitelist

## 2. WorkspaceDB Table Registration

For any new processor, follow this checklist:

1. Define table schema in `WorkspaceDB::createTableXxx()` method
2. Add table name to `$allowedTables` array in `getAllData()`
3. Register creation call in `createTables()` method
4. Add indexes in `createIndexes()` if performance-critical
5. Add export method in JSON export chain
6. Create add method: `add{EntityName}()` (e.g., `addPage()`, `addBlogPost()`, `addAttachment()`)
   - Method signature: `public function add{EntityName}( ... ): void`
   - Inserts a single object record into the corresponding table
   - Example: `WorkspaceDB::addPage(...)` inserts into `pages` table

## 3. Filename Conventions

| Component | Location | Pattern | Example |
|-----------|----------|---------|---------|
| Processor | `src/Analyzer/Processor/` | `{Entity}.php` | `Page.php` |
| Composer Processor | `src/Composer/Processor/` | `{Entity}.php` | `Pages.php` |
| Converter | `src/Converter/Processor/` | `{Operation}Macro.php` | `CodeMacro.php` |
| Postprocessor | `src/Converter/Postprocessor/` | `{Fix/Operation}.php` | `FixLineBreaks.php` |
| Preprocessor | `src/Converter/Preprocessor/` | Domain-specific | `HtmlPreprocessor.php` |

## Wiki Title Conventions

- Wiki titles have to be created using `HalloWelt\MigrateConfluence\Utility\TitleBuilder` or `HalloWelt\MediaWiki\Lib\Migration\TitleBuilder`

## 4. Database Relationships

Current entities and their tables:
- **Spaces** → `spaces`, `spaces_descriptions`
- **Pages** → `pages`, `pages_meta`
- **Blog Posts** → `blog_posts`, `blog_posts_meta`
- **Body Contents** → `body_contents`, `body_contents_bodies`
- **Attachments** → `attachments`, `attachments_meta`, `page_attachments`, `additional_attachments`
- **Users** → `users`
- **Comments** → `comments`
- **Labels** → `labels`, `labellings`
- **Content Properties** → `content_properties`
- **Gliffy** → `gliffy`
- **PageTemplates** → `page_templates`, `page_template_contents`

## 5. Adding a New Processor

Steps:
1. Create `src/Analyzer/Processor/{Entity}.php` extending `ProcessorBase`
2. Add table creation to `WorkspaceDB`
3. Register in `ConfluenceAnalyzer::processXML()`
4. Create corresponding Composer processor if needed
5. Create Converter processor if transformation required
