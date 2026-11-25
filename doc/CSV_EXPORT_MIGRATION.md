# CSV export migration – progress tracker

Confluence is dropping XML space exports; only CSV export remains. This
document tracks the work to support CSV as input for the pipeline
(analyze → extract → convert → compose).

Status legend: `[ ]` open, `[~]` in progress, `[x]` done, `[!]` blocked / needs decision.

## Context

- Current input: XML export (`entities.xml`, attachments) read in the analyze step into SQLite.
- Target input: CSV export (format to be documented below).
- Rule: no silent information loss; report gaps in CSV vs. XML data.

## Open questions / decisions

- [ ] Provide a minimal, anonymized sample of the CSV export (structure, files, columns).
- [ ] Which XML entities have no CSV equivalent (history/versions, users, labels, attachments, comments, ...)?
- [ ] Support both XML and CSV, or replace XML?

## Findings

_(none yet)_

## Tasks

### 1. Research
- [ ] Document CSV export structure (files, columns, encoding, separators)
- [ ] Map CSV fields to the current analyzer data model (`src/Analyzer/`, `src/Database/`)
- [ ] List data that is missing in CSV compared to XML

### 2. Analyze step
- [ ] Design CSV reader
- [ ] Populate SQLite DB from CSV (`CREATE TABLE` only, no migrations)
- [ ] Update `doc/ANALYZER_RULES.md`

### 3. Extract / Convert / Compose
- [ ] Verify extractor works on CSV-derived data (`doc/EXTRACTOR_RULES.md`)
- [ ] Verify converter input (page body format in CSV) (`doc/CONVERTER_RULES.md`)
- [ ] Verify composer output unchanged (`doc/COMPOSER_RULES.md`)

### 4. Tests & docs
- [ ] Synthetic CSV fixtures in `tests/phpunit/data/`
- [ ] Unit tests for CSV reader
- [ ] Update `doc/how_to_export_confluence_spaces.md`, `doc/usage.md`, `README.md`
- [ ] `composer run test` and `composer run unittest` pass

## Log

| Date       | Note                          |
|------------|-------------------------------|
| 2026-11-25 | Tracking document created.    |
