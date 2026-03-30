# Copilot Instructions — migrate-confluence

## Project overview

A PHP CLI tool that converts a Confluence space XML export into a MediaWiki XML import. Processing happens in four sequential steps driven by `bin/migrate-confluence`:

```
analyze → extract → convert → compose
```

Each step has a dedicated class (`ConfluenceAnalyzer`, `ConfluenceExtractor`, `ConfluenceConverter`, `ConfluenceComposer`) that inherits from `hallowelt/mediawiki-lib-migration` base classes. The CLI is Symfony Console-based via `HalloWelt\MediaWiki\Lib\Migration\CliApp`.

## Commands

```bash
# Run all coding convention checks (parallel-lint, minus-x, phpcs)
composer test

# Run unit tests
composer unittest

# Run a single test class
vendor/phpunit/phpunit/phpunit --configuration phpunit.xml --filter InfoMacroTest

# Auto-fix code style
composer fix

# Static analysis (phan)
composer lint
```

Pandoc must be installed on the system — it is used by `ConfluenceConverter` (extends `PandocHTML`) to do the HTML→wikitext conversion.

## Build

```bash
composer update --no-dev
box compile   # produces dist/migrate-confluence.phar
```

## Architecture

### Four-step pipeline

| Step | Class | Input | Output |
|------|-------|-------|--------|
| `analyze` | `ConfluenceAnalyzer` | `entities.xml` (Confluence export) | Named data maps in workspace (`.php` files) |
| `extract` | `ConfluenceExtractor` | `entities.xml` + attachment files | Page body files + attachment files in workspace |
| `convert` | `ConfluenceConverter` | Page body files (Confluence Storage XML) | WikiText files |
| `compose` | `ConfluenceComposer` | WikiText files + attachments | `pages.xml`, `comments.xml`, `images/` (MediaWiki import format) |

### Workspace data (`DataBuckets`)

The `DataBuckets` class (from `mediawiki-lib-migration`) serialises named arrays to PHP files in the workspace directory. Key naming convention:
- `analyze-*` — temporary data produced only in the analyze step
- `global-*` — data shared across steps (page/title/attachment maps)
- `extract-*` — data produced by the extract step

`ConversionDataLookup` wraps bucket data as typed helpers used in the convert step. `ConversionDataWriter` writes conversion-time metadata back to the workspace.

### Processor pattern

Every step delegates to a list of processors instantiated inside the main class (`getPreProcessors()`, `getProcessors()`, `getPostProcessors()`).

**Analyzer processors** (`Analyzer/Processor/`, extend `ProcessorBase`):
- Receive an `XMLReader` positioned at an `<object>` element in `entities.xml`.
- Implement `doExecute()` and declare their output keys via `getKeys(): array`.
- Common base helpers: `processPropertyNodes()`, `processElementNode()`, `processCollectionNodes()`.

**Converter processors** (`Converter/Processor/`, implement `IProcessor`):
- Operate on a `DOMDocument` and manipulate Confluence Storage XML before Pandoc runs.
- Three abstract base classes to choose from:
  - `StructuredMacroProcessorBase` — for `<ac:structured-macro>` elements; converts to a `<div class="ac-{name}">` placeholder.
  - `MacroProcessorBase` — same but for legacy `<ac:macro>` elements.
  - `ConvertMacroToTemplateBase` — converts a structured macro directly to `{{WikiTextTemplate|param=value|body=...}}` syntax, inserting `###BREAK###` markers where pandoc would strip newlines.

**Converter pre/postprocessors**:
- `Converter/Preprocessor/` — string manipulation before DOM parsing (e.g., `CDATAClosingFixer`).
- `Converter/Postprocessor/` — regex/string passes on the wikitext output after Pandoc (e.g., restore `###BREAK###` as real newlines, fix multiline tables/templates).

### `###BREAK###` marker convention

Pandoc strips newlines inside inline content. Processors that generate wikitext template syntax (`{{...}}`) insert `###BREAK###\n` as a placeholder. `FixMultilineTemplate` and `FixMultilineTable` postprocessors replace these with actual newlines.

### Broken content categories

When a processor cannot fully handle content, it emits a MediaWiki category tag rather than failing:
```
[[Category:Broken_macro/macro-name]]
[[Category:Broken_link]]
[[Category:Broken_image]]
```
`getBrokenMacroCategroy()` (note the typo — keep it consistent) is the helper method on the macro processor bases.

### Adding a new macro processor

1. Create a class in `Converter/Processor/` extending one of the three bases above.
2. Implement `getMacroName(): string` (returns the Confluence `ac:name` value).
3. Register the processor in `ConfluenceConverter::getProcessors()` (or `getPreProcessors()`/`getPostProcessors()`).
4. If the macro maps to a MediaWiki template, add the template wikitext file in `Composer/_defaultpages/Template/`.
5. Add a test with XML fixtures in `tests/phpunit/data/` following the `{feature}-input.xml` / `{feature}-output.xml` naming pattern.

## Key conventions

- **Namespace**: `HalloWelt\MigrateConfluence\` mapped to `src/`; tests use `HalloWelt\MigrateConfluence\Tests\`.
- **PHP version**: 8.5 (see CI).
- **Coding style**: MediaWiki coding standards (`mediawiki/mediawiki-codesniffer`). Tabs for indentation, single-space padding inside `( )` on control structures and function calls.
- **`factory` static method**: every main class (`ConfluenceAnalyzer`, etc.) exposes a `static factory(...)` method; this is how `bin/migrate-confluence` registers them in the `CliApp` config array.
- **No constructor injection** for processors — they are instantiated directly in the `get*Processors()` methods of the orchestrating class and configured via setter calls (`setConfig`, `setOutput`, `setLogger`).
- **Test fixtures**: processor tests extend `TestCase`, load an `*-input.xml` and `*-output.xml` pair from `tests/phpunit/data/`, run `process(DOMDocument)`, then assert `$dom->saveXML()` matches the expected output exactly.
- **`phpunit.xml`** (without leading dot) is the config file used by `composer unittest`; the `--configuration` flag accepts either name.
