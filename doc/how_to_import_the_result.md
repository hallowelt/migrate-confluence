# Importing the Migrated Data

You converted your data by [running the migration tool](./usage.md). Now it’s time to reap
the harvest and import the content into your MediaWiki instance.

The migration tool adds helper scripts for the import. You can choose to use them or issue the
commands yourself for maximum control. We describe both processes below.

> **Note:** For the file import you need the extension [BlueSpiceDistributionConnector](https://www.mediawiki.org/wiki/Extension:DistributionConnector) with minimum version 5.1.9 or 5.2.5 installed. See your wiki’s [Special:Version](https://en.wiki5.bluespice.com/wiki/Special:Version) page to check the requirement.

## Manual Import

1. Copy the directory `workspace/result` directory (e.g. `/tmp/confluence/workspace/result/`) to your target wiki server (e.g. `/tmp/result`)
2. Go to your MediaWiki installation directory. This is the folder that contains the `index.php` file and the folders `maintenance` and `extensions`.
3. Make sure you have the target namespaces set up properly. See the files `result/*/deployment.txt` for a list of required namespaces.
4. Make sure [`$wgFileExtensions`](https://www.mediawiki.org/wiki/Manual:$wgFileExtensions) is set up properly. Again, see `result/*/deployment.txt` for reference.
5. Use `php extensions/BlueSpiceDistributionConnector/maintenance/importFiles.php --src=/tmp/result/files.xml` to first import all attachment files and images
6. Use `php maintenance/importDump.php /tmp/result/pages.xml` to import the actual pages. Use the same command to import `blogs.xml`, `page-talk.xml`, `blog-talk.xml`, `templates.xml`, and all other `*.xml` files **apart from** `user.xml`. This file can not be imported and is just for making user data available.

## Import with helper scripts

> **Note:** For a detailed description of the composer modes and their output directory
> layout, see [Composer Output Structure](./composer_output_structure.md).

Two helper scripts in `result/*/` automate the import steps from above:

* `spaceimport.sh` imports a single namespace directory. Use this if you import all data into the same wiki.
* `wikiimport.sh` imports all namespace directories of one wiki. Use this if you import data into several wiki instances.

Both handle split output as well, e.g. `pages-00000001.xml`,
`pages-00000002.xml`, ...

**Common options:**

| Option | Description |
| --- | --- |
| `--wiki-root=PATH` | Required. Path to the MediaWiki root directory. |
| `--src=PATH` | Directory to import. Defaults to the directory the script is located in. |
| `--add-default` | Also import `default-files*.xml` and `default-pages*.xml`. For wiki-based output they are read from `_shared`; for namespace-based output they are read from the namespace directory. |
| `--dry` | Dry run. Only print the import commands so you can verify the paths. |
| `--sfr=NAME` | MediaWiki wiki instance, forwarded to both import maintenance scripts. Omit it for the default wiki. |

Import order per namespace directory:

1. `default-files*.xml` (only with `--add-default`)
2. `default-pages*.xml` (only with `--add-default`)
3. `files*.xml`
4. `templates*.xml`
5. `pages*.xml`
6. `page-talk*.xml`
7. `blogs*.xml`
8. `blog-talk*.xml`
9. `enhanced-sidebar*.xml`, containing the `MediaWiki:Sidebar.json` page for a sidebar that reflects the Confluence space navigation

Only `pages*.xml` is mandatory, all other groups are skipped with a note when
they are missing. `user.xml` is intentionally ignored.

### spaceimport.sh

Expects the namespace based composer output:

```bash
result/<namespace>/{default-files,default-pages,files,templates,pages,page-talk,blogs,blog-talk}.xml
result/<namespace>/default-images/*
```

`--src` points to the namespace directory. Default files and pages are imported
from the same directory when `--add-default` is used:

```bash
spaceimport.sh --wiki-root=/tmp/mediawiki --src=/tmp/result/ABC --add-default
```

### wikiimport.sh

Expects the wiki based composer output:

```bash
result/<wiki-name>/<namespace>/{files,templates,pages,page-talk,blogs,blog-talk}.xml
result/<wiki-name>/_shared/{default-files,default-pages}.xml
result/<wiki-name>/_shared/default-images/*
```

`--src` points to the wiki directory, every namespace directory inside it is
imported and the `_shared` data is imported once per wiki:

```bash
wikiimport.sh --wiki-root=/tmp/mediawiki --src=/tmp/result/MyWiki --sfr=MyWiki --add-default
```

If `--add-default` is used but no `_shared` directory exists, both scripts print
a warning and continue.

## Final Touches after the Import

Whether you use the manual or the script method, you should now run
`php maintenance/rebuildall.php` and update your MediaWiki search index afterwards.

You can now [evaluate the migration result](./how_to_evaluate_the_result.md).

## Additional Notes

### Included MediaWiki wikitext templates

The import will create MediaWiki templates in the target wiki, e.g. pages named `Template:Note` etc.
Be aware that those pages **will be overwritten** during the import, if they already exist.

Templates are added to the import data set only if the migration tool comes across a feature (macro, layout, ...) in the export that needs them.

### Included upload files
The migrate-confluence tool may add default files if they are required (e.g. in wiki templates).
Be aware that existing files with this name will not be overwritten. This might influence the depiction on result pages.

If you want to update these and other images during the import, consider the `--overwrite` flag of the `importFiles.php` script.

### MediaWiki settings
In case your pages contain a lot of external images (`<img />` elements), be aware that MediaWiki does not show them by default. You'd need to configure `$wgAllowExternalImages`.
Read https://www.mediawiki.org/wiki/Manual:$wgAllowExternalImages for more information.

### Jira interwiki links
Confluence pages that contain Jira macros are converted to use MediaWiki [interwiki links](https://www.mediawiki.org/wiki/Manual:Interwiki). Two separate prefixes are used because Jira issue keys and JQL queries have different URL patterns:

| Interwiki prefix | Purpose | Example URL pattern |
|---|---|---|
| `jira` | Link to a specific Jira issue by key | `https://jira.example.com/browse/$1` |
| `jira-jql` | Link to a Jira issue list filtered by JQL | `https://jira.example.com/issues/?jql=$1` |

Add both entries to the `interwiki` table of your MediaWiki database, or configure them via [`$wgExtraInterlanguageLinkPrefixes`](https://www.mediawiki.org/wiki/Manual:$wgExtraInterlanguageLinkPrefixes) and the interwiki cache. Replace `https://jira.example.com` with the base URL of your Jira instance.

### Required MediaWiki extensions
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

### Recommended MediaWiki extensions
These extensions are not strictly required but are recommended for full compatibility with the migrated content.

1. [WikiMarkdown](https://www.mediawiki.org/wiki/Extension:WikiMarkdown) - Renders `<markdown>` tags produced from Confluence markdown macros
1. [PageLayouts](https://github.com/BlueSpice-Wiki/mediawiki-extensions-PageLayouts/) - Allows multi-column layouts in the visual editor
1. the [BlueSpice Discovery](https://www.mediawiki.org/wiki/Skin:BlueSpiceDiscovery) skin – Allows a customized nested sidebar
