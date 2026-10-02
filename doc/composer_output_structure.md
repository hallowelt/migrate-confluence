# Composer Output Structure

The compose step turns converted workspace data into MediaWiki import XML files. All
migrations are composed on a per-wiki basis by `ConfluenceComposer`, which creates one
output directory per target wiki below `workspace/result`. Each wiki directory contains
namespace directories and one `_shared` directory.

If the migration configuration does not assign Confluence spaces to target wikis (no
`--wikis` CSV supplied to `analyze`), all spaces are grouped under a single implicit wiki
named `default`, so the output structure is identical to a migration with exactly one
configured wiki.

The composer groups Confluence spaces by their target namespace. A namespace group can
contain one or more Confluence space IDs. Default pages and default files are filtered by
those space IDs, so only defaults that were actually registered during conversion are
written.

Example:

```text
workspace/result/
	manifest.json
	full-migration-wiki/
		_shared/
			default-images/
			default-files.xml
			default-pages.xml
		CON/
			images/
			blog-talk.xml
			blogs.xml
			files.xml
			invalid_attachments.log
			invalid_blog_posts.log
			invalid_page_templates.log
			invalid_pages.log
			page-talk.xml
			pages.xml
			spaceimport.sh
			templates.xml
			users.xml
		DEVOPS/
			images/
			blog-talk.xml
			blogs.xml
			files.xml
			invalid_attachments.log
			invalid_blog_posts.log
			invalid_page_templates.log
			invalid_pages.log
			page-talk.xml
			pages.xml
			spaceimport.sh
			templates.xml
			users.xml
		deployment.txt
		namespace_import_config.json
		wikiimport.sh
```

The first-level directory is the target wiki name from the wiki mapping configuration.
Inside it, each namespace used by that wiki gets its own namespace directory.

## Wiki-scoped Defaults

For wiki-based composition, default content is written once per wiki into the wiki-local
`_shared` directory:

```text
workspace/result/<wiki-name>/_shared/default-pages.xml
workspace/result/<wiki-name>/_shared/default-files.xml
workspace/result/<wiki-name>/_shared/default-images/
```

The `_shared` directory contains defaults registered by all spaces assigned to that wiki,
across all namespaces of that wiki. Default file binaries are stored in `default-images`,
next to `default-files.xml`.

For example, if wiki `full-migration-wiki` contains namespace `CON` with space `10` and
namespace `DEVOPS` with spaces `20` and `30`, then
`full-migration-wiki/_shared/default-pages.xml` contains the union of registered default
pages for spaces `10`, `20`, and `30`.

Defaults are not copied from a global shared directory. They are generated for the target
wiki from the relevant space IDs.

## Namespace Content Inside a Wiki

Each namespace directory below the wiki contains only namespace-local migration output:

```text
workspace/result/<wiki-name>/<namespace>/pages.xml
workspace/result/<wiki-name>/<namespace>/files.xml
workspace/result/<wiki-name>/<namespace>/images/
workspace/result/<wiki-name>/<namespace>/blogs.xml
workspace/result/<wiki-name>/<namespace>/templates.xml
workspace/result/<wiki-name>/<namespace>/page-talk.xml
workspace/result/<wiki-name>/<namespace>/blog-talk.xml
```

Default pages and default files are not written to these namespace directories in
wiki-based composition. They belong to `<wiki-name>/_shared`.

## Default Pages and Default Files

Default pages and default files are registered during conversion by converter processors
that emit templates or helper files. The composer writes only registered defaults.

### Default Pages

Default page source files live below:

```text
src/Composer/_defaultpages/
```

The first directory below `_defaultpages` is the target namespace. For example:

```text
src/Composer/_defaultpages/Template/TagSearch
```

is written as:

```text
Template:TagSearch
```

A directory can define a default page body with a `wikitext` file:

```text
src/Composer/_defaultpages/Template/Folder/wikitext
```

This is written as:

```text
Template:Folder
```

Additional sibling files are written as subpages of the same registered default page:

```text
src/Composer/_defaultpages/Template/Folder/style.css
```

is written as:

```text
Template:Folder/Style.css
```

The sibling file is included when the parent default page (`Folder`) was registered.

### Default Files

Default file source files live below:

```text
src/Composer/_defaultfiles/
```

A default file is written only when it was registered for one of the relevant space IDs.
The file itself is stored below the `default-images/` directory placed next to
`default-files.xml` and referenced from that XML file.

Example:

```text
workspace/result/<wiki-name>/_shared/default-files.xml
workspace/result/<wiki-name>/_shared/default-images/<filename>
```

## Manifest

`workspace/result/manifest.json` is written once per migration (not once per wiki). It
lists every target wiki produced by the migration, the file extensions encountered in it,
and the deployment scripts to run for that wiki, for example:

```json
{
    "source_system": "confluence",
    "package_id": "customer-x-2026-08-10",
    "target": {
        "wikis": [
            {
                "sfr": "full-migration-wiki",
                "file_extensions": ["pdf", "png"],
                "scripts": [
                    "./result/full-migration-wiki/wikiimport.sh --sfr=full-migration-wiki --add-default",
                    "php /app/bluespice/w/maintenance/rebuildall.php --sfr=full-migration-wiki"
                ]
            }
        ]
    }
}
```

## Namespace Import Config

`workspace/result/<wiki-name>/namespace_import_config.json` is written once per wiki and
lists the MediaWiki namespace IDs to create for that wiki's import. Namespace IDs start at
`3000` and increase in steps of `2` (`3000`, `3002`, `3004`, ...), assigned in the order
namespaces were first encountered for that wiki.

```json
{
    "3000": {
        "name": "CON",
        "subpages": true,
        "content": true,
        "pagetemplates": true,
        "visualeditor": true,
        "smw": true,
        "commentstreams": true
    },
    "3002": {
        "name": "DEVOPS",
        "subpages": true,
        "content": true,
        "pagetemplates": true,
        "visualeditor": true,
        "smw": true,
        "commentstreams": true
    }
}
```

The target main namespace (`NS_MAIN`, i.e. a space mapped to an empty wiki namespace) does
not need an import configuration entry and is always excluded. If a wiki ends up with no
namespace left after excluding `NS_MAIN` (for example a single-space migration mapped
entirely to the main namespace), `namespace_import_config.json` is not written at all for
that wiki.

## Import Helpers

The composer also writes shell helper scripts for importing generated output.

- `spaceimport.sh` is written into namespace directories.
- `wikiimport.sh` is written into wiki directories.

Run the namespace import helper from a namespace directory for namespace-local content.
Run the wiki import helper from a wiki directory; it knows about the wiki-local `_shared`
directory and can import default pages and files before namespace-local content when
requested. Default file XML references binaries from the `default-images` directory
placed next to that XML file.
