# Agent Instructions

This is a command line tool to convert the contents of one or several Confluence spaces in 4 steps into
a MediaWiki import data format. The XML export of a Confluence space is read into an
SQLite DB in the analyze step. The extract step creates necessary infos, especially
the new wiki titles of pages. The convert step changes the markup to wikitext. The
compose step takes the data and produces XML files ready for import into MediaWiki/BlueSpice.

## Rules

We aim for high fidelity in the migration result. Unless explicitly specified
we try to migrate a piece of information as faithfully as possible. Report
back, if this is a problem or if you need a decision. The code must never
silently lose input or map several items into one output, unless this was
confirmed as a wanted output enhancement by a human.

### Rules files

Read the according file(s) from the following list before making changes in the
Analyzer/Extractor/Converter/Composer folders (or the according commands) in
`src`:

- `doc/ANALYZER_RULES.md`
- `doc/EXTRACTOR_RULES.md`
- `doc/CONVERTER_RULES.md`
- `doc/COMPOSER_RULES.md`

## Layout

- `bin/migrate-confluence` - primary executable
- `src/Command/` - CLI entry points (Analyze, Extract, Convert, Compose, ValidateConfig, ...)
- `src/Analyzer/`, `src/Extractor/`, `src/Converter/`, `src/Composer/` - pipeline stage logic
- `src/Database/` - SQLite access layer
- `src/Utility/` - shared helpers

## PHP code quality

All new or modified PHP code must pass:

```
composer run test
```

This runs parallel-lint, minus-x, and phpcs (style/sniffs). Run `composer run test` before considering a change done.

If phpcs reports style issues, `composer run fix` (minus-x + phpcbf) auto-fixes most of them.

Do not run `composer run lint`! It has known issues at the moment.

## Doc comments

Doc comments must not repeat what the PHP signature already says.

- Omit `@param` and `@return` tags whose type is identical to the native type
  declaration and that have no description.
- Keep a tag when it adds information: element types of arrays
  (`@return DOMElement[]`, `@param array<string, int> $map`), a more specific
  type than the native one, or a description of the meaning or allowed values.
- Keep `@throws` tags.
- If a doc comment would be empty after this, omit it entirely. Do not write a
  summary that just restates the method name ("Gets the parameters").
- Only apply this to code you write or modify. Do not clean up doc comments in
  unrelated code, to keep diffs focused.

### Unit tests

Unit tests live in `tests/phpunit/` and are run with:

```
composer run unittest
```

Run the narrowest relevant subset after changes; run the full suite when
touching shared code. Make sure that any temporary files and folders are
cleaned up again.

Never weaken, skip or delete an existing test to make it pass. If a test expectation seems wrong, report it instead.
New converter behavior needs a test with input markup and expected wikitext.

## Code Creation

If you write new code, make sure that it produces valid UTF-8 encoded text.
This is especially important in the context of text modifications like
`substr()`.

If you create new MediaWiki templates, document their parameters in-line with
`<templatedata>` sections. Make sure to register them with
`HalloWelt\MigrateConfluence\Converter\DataWriter\AbstractDirectDataWriter::registerDefaultPage()`
in the processor that needs them.

If you change output file layouts in `src/Composer`, read
`doc/composer_output_structure.md` before for guidance.

If you handle DB queries: Never write migrations, schema tests or `ALTER TABLE`
statements. Always assume that the `CREATE TABLE` statements are sufficient.
The database will always be created afresh in the `analyze` step and is
expected to remain in the exact same state during the other three steps.

Never edit code in the `vendor` folder. However, libraries in the `vendor/hallowelt` folder
will accept upstream change requests. If you happen to find that an edit in one of those
projects will simplify your implementation, especially in `vendor/hallowelt/mediawiki-lib-migration/src`,
report this finding back to the user.

## Workspace boundaries

Stay inside the repository root at all times. Do not read, write, list or
search anything outside of it. Never run commands like `find /`, `ls /` or
`grep -r /`, and do not try to locate binaries outside the repo.

Use paths relative to the repository root.
For temporary files and test output, use `.tmp/` in the repository root (it is git-ignored). Do not use `/tmp` or `sys_get_temp_dir()`. Delete what you created when you are done.
`php` and `composer` are available on the PATH. Run tools from `vendor/bin/`.

## Git and dependencies
Do not commit, push, or create branches unless asked.
Do not add, remove or update Composer dependencies, and do not modify `composer.lock`, without asking first.

## Real customer data

Never open, read, search, or run the pipeline on real Confluence exports or
on any output derived from them (SQLite DBs, converted wikitext, compose
output). This applies even if such files are present in the workspace or a
user path points to them.

If a bug can only be understood from real input, stop and ask the user to
provide a minimal, anonymized snippet. Then build a synthetic test case from
that snippet.