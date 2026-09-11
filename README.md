# Migrate Confluence XML export to MediaWiki import data

This is a command line tool to convert the contents of a Confluence space into a MediaWiki import data format. See also the [official BlueSpice Helpdesk entry](https://en.wiki.bluespice.com/wiki/Confluence_migration).

## Docker

The migrate confluence tool is available as [docker image](https://hub.docker.com/r/bluespice/migrate-confluence).

## Workflow

1. Export spaces from Confluence: See [`doc/how_to_export_confluence_spaces.md`](doc/how_to_export_confluence_spaces.md) for a documentation of the export process.
2. Run the tool on the exported data: See [`doc/usage.md`](doc/usage.md) for how to invoke the software. The result is a set of file to import into MediaWiki.
3. Import into MediaWiki: See [`doc/how_to_import_the_result.md`](doc/how_to_import_the_result.md) to get the data into your wiki.
4. Manual post-import maintenance: See [`doc/how_to_evaluate_the_result.md`](doc/how_to_evaluate_the_result.md) for tips how to check the faithfulness of the migration result.

## Config file

It is possible to use a yaml file to configure the commands. See [`doc/configuration.md`](doc/configuration.md) for details.

## TODO
* Remove line breaks and arbitrary formatting (e.g. `<b>`) from headings
* Mask external images (`<img />`)
* Merge multiple `<code>` lines into `<pre>`
* Remove bold/italic formatting from wikitext headings (e.g. `=== '''Some heading''' ===`)
* Fix unconverted HTML lists in wikitext (e.g. `<ul><li>==== Lorem ipsum ====</li><li>'''<span class="confluence-link"> </span>[[Media:Some_file.pdf]]'''</li></ul><ul>`)
* Remove empty confluence storage format fragments (e.g. `<span class="confluence-link"> </span>`, `<span class="no-children icon">`)
