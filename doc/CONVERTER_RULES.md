# Coding Rules for Converter Component

The Converter transforms Confluence Storage XML content into MediaWiki WikiText format. It processes DOM documents through processors, preprocessors, and postprocessors.

## 1. Processor Pattern

Converter processors handle transformation of specific Confluence elements or macros.

### File Naming & Location
- **Macro Processors**: `src/Converter/Processor/{MacroName}Macro.php`
  - Examples: `CodeMacro.php`, `TocMacro.php`, `PanelMacro.php`
- **Content Processors**: `src/Converter/Processor/{ElementType}.php`
  - Examples: `Image.php`, `PageLink.php`, `UserLink.php`, `Emoticon.php`
- **Base Classes**: `src/Converter/Processor/{BaseType}Base.php`
  - Examples: `MacroProcessorBase.php`, `StructuredMacroProcessorBase.php`, `LinkProcessorBase.php`

### Class Convention
- Implements: `IProcessor`
- Extends: One of the base classes (`MacroProcessorBase`, `StructuredMacroProcessorBase`, `LinkProcessorBase`)
- Namespace: `HalloWelt\MigrateConfluence\Converter\Processor`
- Method to implement: `process( DOMDocument $dom ): void`
  - Searches for target elements/macros in the DOM
  - Transforms them using DOM manipulation

### Pattern Specifics
- For macro processors: implement `getMacroName(): string` to specify target macro name
- Use DOM manipulation to locate elements via `getElementsByTagName()`, `getElementsByClassName()`, etc.
- Replace or modify DOM nodes in place
- Handle parameters from `ac:parameter` attributes (Confluence format)

## 2. Preprocessor Pattern

Preprocessors prepare the HTML/DOM **before** macro conversion to fix structural issues.

### File Naming & Location
- HTML Preprocessors: `src/Converter/Preprocessor/html/{Name}.php`
  - Example: `CDATAClosingFixer.php`
- DOM Preprocessors: `src/Converter/Preprocessor/dom/{Name}.php`
  - Examples: `HoistMacroFromHeading.php`, `SanitizeLinkContent.php`, `Table.php`

### Class Convention
- Implements: `IHtmlPreprocessor` or `IDomPreprocessor`
- Namespace: `HalloWelt\MigrateConfluence\Converter\Preprocessor\{html|dom}`
- Method to implement:
  - `IHtmlPreprocessor`: `process( string $html ): string`
  - `IDomPreprocessor`: `process( DOMDocument $dom ): void`

## 3. Postprocessor Pattern

Postprocessors fix content **after** macro conversion and PANDOC HTML-to-WikiText transformation.

### File Naming & Location
- Location: `src/Converter/Postprocessor/{Fix|Operation}.php`
- Examples: `FixLineBreakInHeadings.php`, `FixMultilineTable.php`, `NestedHeadings.php`
- Use `Fix` prefix for bug fixes, descriptive name for enhancements

### Class Convention
- Implements: `IPostprocessor`
- Namespace: `HalloWelt\MigrateConfluence\Converter\Postprocessor`
- Method to implement: `process( string $output ): string`
  - Takes WikiText string as input
  - Returns modified WikiText string
  - Use regex or string manipulation for text-level changes

### Usage Pattern
- Applied in sequence after HTML-to-WikiText conversion
- Each postprocessor should handle one specific concern
- Can be disabled/reordered via configuration

## 4. Processor Registration

All processors must be registered in `ConfluenceConverter::__construct()`:

1. **Processors**: Add to processor instantiation list
   - Order matters (executed in registration order)
2. **Preprocessors**: Add to appropriate preprocessor chain
   - HTML preprocessors before DOM preprocessing
   - DOM preprocessors before macro conversion
3. **Postprocessors**: Add to postprocessor chain
   - Order: Fix issues bottom-up (earlier fixes enable later ones)

## 5. DOM Processing Best Practices

- Use `DOMXPath` for complex queries instead of `getElementsByTagName()`
- Always iterate over a copy of the NodeList before modifying:
  ```php
  $nodes = [];
  foreach ($dom->getElementsByTagName('macro') as $node) {
      $nodes[] = $node;
  }
  foreach ($nodes as $node) {
      // Safe to modify DOM here
  }
  ```
- Replace nodes using `appendChild()` and `removeChild()`
- Set attributes with `setAttribute()`, get with `getAttribute()`
- Create new elements with `createElement()`

## 6. Naming Conventions Summary

| Type | Location | Pattern | Example |
|------|----------|---------|---------|
| Macro Processor | `Processor/` | `{MacroName}Macro.php` | `CodeMacro.php` |
| Content Processor | `Processor/` | `{ElementType}.php` | `Image.php` |
| Processor Base | `Processor/` | `{Type}ProcessorBase.php` | `MacroProcessorBase.php` |
| HTML Preprocessor | `Preprocessor/html/` | `{Name}.php` | `CDATAClosingFixer.php` |
| DOM Preprocessor | `Preprocessor/dom/` | `{Name}.php` | `Table.php` |
| Postprocessor | `Postprocessor/` | `{Fix\|Operation}.php` | `FixLineBreakInHeadings.php` |
