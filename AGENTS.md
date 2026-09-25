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

### Unit tests

Unit tests live in `tests/phpunit/` and are run with:

```
composer run unittest
```

Run the narrowest relevant subset after changes; run the full suite when
touching shared code. Make sure that any temporary files and folders are
cleaned up again.

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
will receive upstream change requests. If you happen to find that an edit in one of those
projects will simplify your implementation, especially in `vendor/hallowelt/mediawiki-lib-migration/src`,
report this finding back to the user.