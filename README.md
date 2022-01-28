# Migrate Confluence XML export to MediaWiki import data

This is a command line tool to convert the contents of a Confluence space into a MediaWiki imprt data format.

## Prerequisites
1. PHP >= 7.4 must be installed
2. The `pandoc` tool must be installed and available in the `PATH` (https://pandoc.org/installing.html)

## Installation
1. Download `migrate-confluence.phar` from https://github.com/hallowelt/migrate-confluence/releases/tag/latest
2. Make sure the file is executable. E.g. by running `chmod +x migrate-confluence.phar`
3. Move `migrate-confluence.phar` to `/usr/local/bin/migrate-confluence` (or somewhere else in the `PATH`)

## Workflow

### Export "space" from Confluence
1. Create an export of your confluence space

Step 1:

<kbd>![Export 1][c001]</kbd>

Step 2:

<kbd>![Export 2][c002]</kbd>

Step 3:

<kbd>![Export 3][c003]</kbd>

2. Save it to a location that is accessbile by this tool (e.g. `/tmp/confluence/input/Confluence-export.zip`)
3. Extract the ZIP file (e.g. `/tmp/confluence/input/Confluence-export`)
	1. The folder should contain the files `entities.xml` and `exportDescriptor.properties`, as well as the folder `attachments`

[c001]: doc/images/Confluence_export_space_001.png
[c002]: doc/images/Confluence_export_space_002.png
[c003]: doc/images/Confluence_export_space_003.png

### Migrate the contents
1. Create the "workspace" directory (e.g. `/tmp/confluence/workspace/` )
2. From the parent directory (e.g. `/tmp/confluence/` ), run the migration commands
	1. Run `migrate-confluence analyze --src input/ --dest workspace/` to create "working files". After the script has run you can check those files and maybe apply changes if required (e.g. when applying structural changes).
	2. Run `migrate-confluence extract --src input/ --dest workspace/` to extract all contents, like wikipage contents, attachments and images into the workspace
	3. Run `migrate-confluence convert --src workspace/ --dest workspace/` (yes, `--src workspace/` ) to convert the wikipage contents from Confluence Storage XML to MediaWiki WikiText
	4. Run `migrate-confluence compose --src workspace/ --dest workspace/` (yes, `--src workspace/` ) to create importable data

If you re-run the scripts you will need to clean up the "workspace" directory!

### Import into MediaWiki
1. Copy the diretory "workspace/result" directory (e.g. `/tmp/confluence/workspace/result/` to your target wiki server (e.g. `/tmp/result`)
1. Go to your MediaWiki installation directory
2. Make sure you have the target namespaces set up properly. See `workspace/space-id-to-prefix-map.php` for reference.
3. Make sure [$wgFileExtensions](https://www.mediawiki.org/wiki/Manual:$wgFileExtensions) is setup properly. See `workspace/attachment-file-extensions.php` for reference.
4. Use `php maintenance/importImages.php /tmp/result/images/` to first import all attachment files and images
5. Use `php maintenance/importDump.php /tmp/result/output.xml` to import the actual pages

You may need to update your MediaWiki search index afterwards.

#### Included MediaWiki wikitext templates
- `AttachmentsSectionEnd`
- `AttachmentsSectionStart`
- `Excerpt`
- `Info`
- `InlineComment`
- `Layout`
- `Layouts.css`
- `Note`
- `Panel`
- `RecentlyUpdated`
- `SubpageList`
- `SubpageListRow`
- `Task`
- `TaskListEnd`
- `TaskListStart`
- `Tip`
- `Warning`

Be aware that those pages may be overwritten by the import if they already exist in the target wiki.

#### Included upload files
- `Icon-info.svg`
- `Icon-note.svg`
- `Icon-tip.svg`
- `Icon-warning.svg`

Be aware that those files may be overwritten by the import if they already exist in the target wiki.

#### Recommended MediaWiki extensions
Some Confluence macros are replaced with MediaWiki templates, that hold extension specific wikitext. This is especially important for features like "Recently updated" or "Subpagelist".

1. Semantic MediaWiki
2. HeaderTabs

### Manual post-import maintenance
#### Cleanup Categories
In the case that the tool can not migrate content or functionality it will create a category, so you can manually fix issues after the import
- `Broken_link`
- `Broken_user_link`
- `Broken_page_link`
- `Broken_image`
- `Broken_layout`
- `Broken_macro/<macro-name>`


## Not migrated
- User identities
- Comments
- Various macros
- Various layouts
- Blog posts
- Files of a space which can not be assigned to a page

## Creating a build
1. Clone this repo
2. Run `composer update --no-dev`
3. Run `box build` to actually create the PHAR file  in `dist/`. See also https://github.com/humbug/box

# TODO
* Reduce multiple linebreaks (`<br />`) to one
* Remove line breaks and arbitrary fromatting (e.g. `<b>`) from headings
* Mask external images (`<img />`)
* Preserve filename of "Broken_attachment"
* Merge multiple `<code>` lines into `<pre>`
* Remove bold/italic formatting from wikitext headings (e.g. `=== '''Some heading''' ===`)
* Fix unconverted HTML lists in wikitext (e.g. `<ul><li>==== Lorem ipsum ====</li><li>'''<span class="confluence-link"> </span>[[Media:Some_file.pdf]]'''</li></ul><ul>`)
* Remove empty confluence storage format fragments (e.g. `<span class="confluence-link"> </span>`, `<span class="no-children icon">`)
