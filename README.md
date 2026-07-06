# Migrate Confluence XML export to MediaWiki import data

This is a command line tool to convert the contents of a Confluence space into a MediaWiki import data format. See also the [official BlueSpice Helpdesk entry](https://en.wiki.bluespice.com/wiki/Confluence_migration).

## Docker

The migrate confluence tool is available as [docker image](https://hub.docker.com/r/bluespice/migrate-confluence).

## Workflow

### Export "space" from Confluence

1. Create an export of your confluence space (one export xml for each space).

    Step 1:

    ![Export 1][c001]

    Step 2:

    ![Export 2][c002]

    Step 3:

    ![Export 3][c003]

2. Save it to a location that is accessbile by this tool (e.g. `/tmp/confluence/Confluence-export.zip`)
3. Create the input directory (e.g. `/tmp/confluence/input`)

    ```
	cd /tmp/confluence
	mkdir input
	```
4. Extract the ZIP file to the `input` folder or a child folder therein, e.g. `/tmp/confluence/input/Confluence-export`.

	The folder should contain the files `entities.xml` and `exportDescriptor.properties` as well as the folder `attachments`.

	```
	unzip Confluence-export.zip -d input
	```

[c001]: doc/images/Confluence_export_space_001.png
[c002]: doc/images/Confluence_export_space_002.png
[c003]: doc/images/Confluence_export_space_003.png

### Migrate the contents

1. Create the "workspace" directory (e.g. `/tmp/confluence/workspace/`)
2. From the main directory (e.g. `/tmp/confluence`), run the migration commands
	1. Run
		
		```
		docker run --rm -v $(pwd):/data bluespice/migrate-confluence:latest analyze --src=/data/input --dest=/data/workspace
		```

		to create "working files". After the script has run you can check those files and maybe apply changes if required (e.g. when applying structural changes).
	2. Run
	
	    ```
		docker run --rm -v $(pwd):/data bluespice/migrate-confluence:latest extract --src=/data/input --dest=/data/workspace
		```
		
		to extract all contents, like wikipage contents, attachments and images into the workspace
	3. Check database tables `logging`, `page_invalid_titles`, `blog_post_invalid_titles`, `page_template_invalid_titles` and `attachment_invalid_titles`. Modifiy titles if necessary.
	4. Run
	
	    ```
		docker run --rm -v $(pwd):/data bluespice/migrate-confluence:latest convert --src=/data/workspace --dest=/data/workspace
		```
		
		(yes, `--src=/data/workspace/` ) to convert the wikipage contents from Confluence Storage XML to MediaWiki WikiText. For large spaces, see [Parallel convert](#parallel-convert) below.
	5. Check database tables `logging`, `body_contents`, `page_template_contents`
	5. Run

		```
		docker run --rm -v $(pwd):/data bluespice/migrate-confluence:latest compose --src=/data/workspace --dest=/data/workspace
		```
		
		(yes, `--src=/data/workspace/` ) to create importable data
	6. Check the log files in workspace directory for errors, especially the `skipped_pages.log`. Pages logged in this file are not part of the mediawiki import data.

Important: If you re-run the scripts you will need to clean up the "workspace" directory!

### Import into MediaWiki

> **Note:** For the file import you need the extension [BlueSpiceDistributionConnector](https://www.mediawiki.org/wiki/Extension:DistributionConnector) with minimum version 5.1.9 or 5.2.5 installed. See your wiki’s [Special:Version](https://en.wiki5.bluespice.com/wiki/Special:Version) page to check the requirement.

1. Copy the diretory "workspace/result" directory (e.g. `/tmp/confluence/workspace/result/`) to your target wiki server (e.g. `/tmp/result`)
2. Go to your MediaWiki installation directory
3. Make sure you have the target namespaces set up properly. See `workspace/deployment.log` for a list of required namespaces.
4. Make sure [`$wgFileExtensions`](https://www.mediawiki.org/wiki/Manual:$wgFileExtensions) is set up properly. See `workspace/deployment.log` for reference.
5. Use `php extensions/BlueSpiceDistributionConnector/maintenance/importFiles.php --src=/tmp/result/files.xml` to first import all attachment files and images
6. Use `php maintenance/importDump.php /tmp/result/pages.xml` to import the actual pages. Use the same command to import `blogs.xml`, `comments.xml` and `templates.xml`, but not `user.xml`. This file can not be imported and is just for making user data available.

#### Import helper script
To simplify imports there is a helper script at `src/Composer/_shell/import.sh`.

Run it from your MediaWiki root directory and pass the result namespace directory with `--src`:

```bash
./src/Composer/_shell/import.sh --src=/tmp/result/ABC
```

The script imports files in this order:

1. `files.xml`
2. `blogs.xml`
3. `comments.xml`
4. `templates.xml`
5. `pages.xml`

`user.xml` is intentionally ignored.

You may need to run `php maintenance/rebuildall.php` and update your MediaWiki search index afterwards.

### Additional Features

#### Config file
It is possible to use a yaml file to configure the commands. As an example see `doc/config.sample.yaml`.
If the configuration file is placed in, e.g., `/tmp/confluence/config.yaml`, it can be applied by adding the option `--config=/data/config.yaml` to the comments above.

Not all parameters of `config.sample.yaml` have to be used in the config file. If something is not part of it the default will be used.

#### Parallel convert

For large Confluence spaces the `convert` step can be slow. You can speed it up by running multiple worker processes in parallel using the `--workers` option.

```bash
docker run --rm -v $(pwd):/data bluespice/migrate-confluence:latest convert \
  --src=/data/workspace --dest=/data/workspace \
  --workers=4
```

The command spawns the requested number of child processes automatically. Each worker handles a disjoint slice of the file list, so every file is converted exactly once. Progress lines are prefixed with `[Worker N]` so you can follow each process individually. If any worker fails the command exits with a non-zero status and reports which workers were affected.

Choose `--workers` based on the number of available CPU cores. A value between 2 and 8 is typical; there is no benefit in exceeding the number of cores on your machine.

> **Note:** `--workers=1` (the default) behaves identically to running without the option — no child processes are spawned.

#### Extension:NSFileRepo compatibility
The migrate-confluence tool supports compatibility for the mediawiki extension https://www.mediawiki.org/wiki/Extension:NSFileRepo which restricts access files and images to a given set of user groups associated with protected namespaces.

Activate the feature in the config file:

```yaml
config:
	ext-ns-file-repo-compat: true
```


#### User spaces
In confluence user spaces are protected. In MediaWiki this is not possible for namespace `User`. Therefore user spaces are migrated to a namespace `User<username>` which can be protected in `BlueSpice for MediaWiki`.

#### Included MediaWiki wikitext templates

The import will create templates in the target wiki, e.g. a page named `Template:Note`.
Be aware that those pages will be overwritten by the import, if they already exist.

The list of included templates is in `./src/Composer/_defaultpages/Template/`. It includes, amongst others:

- `AttachmentsSectionEnd`
- `AttachmentsSectionStart`
- `Details`
- `DetailsSummary`
- `Excerpt`
- `ExcerptInclude`
- `Info`
- `InlineComment`
- `Note`
- `Panel`
- `RecentlyUpdated`
- `SubpageList`
- `SubpageListRow`
- `Tip`
- `Warning`
- `PageTree`
- `SpaceDetails`
- `ViewFile`
- (and more)

#### Included upload files

The import will always upload the following files:

- `Icon-info.svg`
- `Icon-note.svg`
- `Icon-tip.svg`
- `Icon-warning.svg`

Be aware that existing files with this name will not be overwritten. This might influence the depiction on result pages.

If you want to update these and other images during the import, consider the the `--overwrite` flag of the `importFiles.php` script.

#### MediaWiki settings
In case your pages contain a lot of external images (`<img />` elements), be aware that MediaWiki does not show them by default. You'd need to configure `$wgAllowExternalImages`.
Read https://www.mediawiki.org/wiki/Manual:$wgAllowExternalImages for more information.

#### Jira interwiki links
Confluence pages that contain Jira macros are converted to use MediaWiki [interwiki links](https://www.mediawiki.org/wiki/Manual:Interwiki). Two separate prefixes are used because Jira issue keys and JQL queries have different URL patterns:

| Interwiki prefix | Purpose | Example URL pattern |
|---|---|---|
| `jira` | Link to a specific Jira issue by key | `https://jira.example.com/browse/$1` |
| `jira-jql` | Link to a Jira issue list filtered by JQL | `https://jira.example.com/issues/?jql=$1` |

Add both entries to the `interwiki` table of your MediaWiki database, or configure them via [`$wgExtraInterlanguageLinkPrefixes`](https://www.mediawiki.org/wiki/Manual:$wgExtraInterlanguageLinkPrefixes) and the interwiki cache. Replace `https://jira.example.com` with the base URL of your Jira instance.

#### File revisions

The tool has experimental support for file revisions. Enable them with the
config option

```yaml
config:
    # enable BETA support for file revisions
    include-history: true
```

There is a good chances for problems and edge-cases, though. Take care to
validate the output.

#### Required MediaWiki extensions
The output generated by the tool contains certain elements that need additonal extensions to be enabled.

1. [TemplateStyles](https://www.mediawiki.org/wiki/Extension:TemplateStyles)
2. [ParserFunctions](https://www.mediawiki.org/wiki/Extension:ParserFunctions)
3. [DateTimeTools](https://www.mediawiki.org/wiki/Extension:DateTimeTools)
4. [Checklists](https://www.mediawiki.org/wiki/Extension:Checklists)
5. [SimpleTasks](https://www.mediawiki.org/wiki/Extension:SimpleTasks)
6. [EnhancedUploads](https://www.mediawiki.org/wiki/Extension:EnhancedUploads)
7. [Semantic MediaWiki](https://www.semantic-mediawiki.org/wiki/Semantic_MediaWiki)
8. [HeaderTabs](https://www.mediawiki.org/wiki/Extension:HeaderTabs)
9. [SubPageList](https://www.mediawiki.org/wiki/Extension:SubPageList)
10. [TableTools](https://www.mediawiki.org/wiki/Extension:TableTools)

#### Recommended MediaWiki extensions
These extensions are not strictly required but are recommended for full compatibility with the migrated content.

1. [WikiMarkdown](https://www.mediawiki.org/wiki/Extension:WikiMarkdown) - Renders `<markdown>` tags produced from Confluence markdown macros
1. [PageLayouts](https://github.com/BlueSpice-Wiki/mediawiki-extensions-PageLayouts/) - Allows multi-column layouts in the visual editor

### Manual post-import maintenance
#### Cleanup Categories
In the case that the tool cannot migrate content or functionality it will create a category, so you can manually fix issues after the import
- `Broken_link`
- `Broken_user_link`
- `Broken_page_link`
- `Broken_image`
- `Broken_layout`
- `Broken_macro/<macro-name>`


## Not migrated
- User identities
- Various macros
- Some layouts
- Files of a space which can not be assigned to a page

# TODO
* Remove line breaks and arbitrary formatting (e.g. `<b>`) from headings
* Mask external images (`<img />`)
* Merge multiple `<code>` lines into `<pre>`
* Remove bold/italic formatting from wikitext headings (e.g. `=== '''Some heading''' ===`)
* Fix unconverted HTML lists in wikitext (e.g. `<ul><li>==== Lorem ipsum ====</li><li>'''<span class="confluence-link"> </span>[[Media:Some_file.pdf]]'''</li></ul><ul>`)
* Remove empty confluence storage format fragments (e.g. `<span class="confluence-link"> </span>`, `<span class="no-children icon">`)
