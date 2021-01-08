# Migrate Confluence XML export to MediaWiki import data

This is a command line tool to convert the contents of a Confluence space into a MediaWiki imprt data format.

## Prerequisites
1. PHP 7.x must be installed
2. The `pandoc` tool must be installed and available in the `PATH` (https://pandoc.org/installing.html)

## Workflow

### Export "space" from Confluence
1. Create an export of your confluence space

Step 1:
![Export 1][c001]

Step 2:
![Export 2][c002]

Step 3:
![Export 3][c003]

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
	3. Run `migrate-confluence convert --src workspace/ --dest workspace/` to convert the wikipage contents from Confluence Storage XML to MediaWiki WikiText
	4. Run `migrate-confluence compose --src workspace/ --dest workspace/` to create importable data

If you re-run the scripts you will need to clean up the "workspace" directory!

### Import into MediaWiki
1. Copy the diretory "workspace/result" directory (e.g. `/tmp/confluence/workspace/result/` to your target wiki server (e.g. `/tmp/result`)
1. Go to your MediaWiki installation directory
2. Make sure you have the target namespaces set up properly
3. Use `php maintenance/importImages.php /tmp/result/images/` to first import all attachment files and images
4. Use `php maintenance/importDump.php /tmp/result/output.xml` to import the actual pages

You may need to update your MediaWiki search index afterwards.

### Manual post-import maintenance
#### Cleanup Categories
In the case that the tool can not migrate content or functionality it will create a category, so you can manually fix issues after the import
- `Broken_link`
- `Broken_user_link`
- `Broken_image`
- `Broken_layout`
- `Broken_macro/<macro-name>`


## Not migrated
- User identities
- Comments
- Various macros
- Various layouts
- Blog posts

## Creating a PHAR
See https://github.com/humbug/box

# TODO
* Reduce multiple linebreaks (`<br />`) to one
* Remove line breaks and arbitrary fromatting (e.g. `<b>`) from headings
* Mask external images (`<img />`)
* Preserve filename of "Broken_attachment"
* Add `wikitable` as default class to `<table>`
* Merge multiple `<code>` lines into `<pre>`