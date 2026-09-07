# Composer Output Structure

The compose step turns converted workspace data into MediaWiki import XML files.
There are two composer modes, depending on whether the migration configuration assigns
Confluence spaces to target wikis.

- `NamespaceBasedComposer` is used when no wiki mapping is configured.
- `WikiBasedComposer` is used when spaces are assigned to one or more target wikis.

Both composers group Confluence spaces by their target namespace. A namespace group can
contain one or more Confluence space IDs. Default pages and default files are filtered by
those space IDs, so only defaults that were actually registered during conversion are
written.

## NamespaceBasedComposer

`NamespaceBasedComposer` creates one output directory per target namespace directly below
`workspace/result`.

Example:

```text
workspace/result/
  CON/
    default-files.xml
    default-pages.xml
    default-images/
    files.xml
    pages.xml
    templates.xml
    page-talk.xml
    blog-talk.xml
    users.xml
    invalid_pages.log
    invalid_blog_posts.log
    invalid_attachments.log
    invalid_page_templates.log
    deployment.txt
    spaceimport.sh
    images/
```

The namespace directory name is the target MediaWiki namespace. If a namespace contains
more than one Confluence space, all space IDs in that namespace are processed together.

### Namespace-scoped Defaults

For namespace-based composition, default content is written into the namespace directory:

```text
workspace/result/<namespace>/default-pages.xml
workspace/result/<namespace>/default-files.xml
workspace/result/<namespace>/default-images/
```

These files contain only default pages and files registered for the space IDs assigned to
that namespace. Default file binaries are stored in `default-images`, next to
`default-files.xml`.

For example, if namespace `CON` contains spaces `10` and `20`, then
`CON/default-pages.xml` contains the union of registered default pages for spaces `10` and
`20`. It does not contain defaults registered only by a different namespace.

### Namespace Content

The namespace directory also contains the regular import XML files for that namespace:

- `pages.xml`: current page content
- `files.xml`: page and blog post attachments
- `templates.xml`: Confluence page templates
- `page-talk.xml`: page comments
- `blog-talk.xml`: blog post comments
- `users.xml`: exported user metadata for lookup/reference

When split output is enabled, files can be written as numbered chunks, for example
`pages-00000001.xml`, `pages-00000002.xml`, and so on.

## WikiBasedComposer

`WikiBasedComposer` creates one output directory per target wiki below
`workspace/result`. Each wiki directory contains namespace directories and one `_shared`
directory.

Example:

```text
workspace/result/
  full-migration-wiki/
    _shared/
      default-files.xml
      default-pages.xml
      default-images/
    CON/
      files.xml
      pages.xml
      templates.xml
      page-talk.xml
      blog-talk.xml
      users.xml
      invalid_pages.log
      invalid_blog_posts.log
      invalid_attachments.log
      invalid_page_templates.log
      spaceimport.sh
    DEVOPS/
      files.xml
      pages.xml
      templates.xml
      page-talk.xml
      blog-talk.xml
      users.xml
      invalid_pages.log
      invalid_blog_posts.log
      invalid_attachments.log
      invalid_page_templates.log
      spaceimport.sh
    deployment.txt
    wikiimport.sh
```

The first-level directory is the target wiki name from the wiki mapping configuration.
Inside it, each namespace used by that wiki gets its own namespace directory.

### Wiki-scoped Defaults

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

### Namespace Content Inside a Wiki

Each namespace directory below the wiki contains only namespace-local migration output:

```text
workspace/result/<wiki-name>/<namespace>/pages.xml
workspace/result/<wiki-name>/<namespace>/files.xml
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

Examples:

```text
workspace/result/<namespace>/default-files.xml
workspace/result/<namespace>/default-images/<filename>

workspace/result/<wiki-name>/_shared/default-files.xml
workspace/result/<wiki-name>/_shared/default-images/<filename>
```

## Import Helpers

The composer also writes shell helper scripts for importing generated output.

- `spaceimport.sh` is written into namespace directories.
- `wikiimport.sh` is written into wiki directories.

For namespace-based output, run the namespace import helper from a namespace directory.
For wiki-based output, run the wiki import helper from a wiki directory. The wiki import
helper knows about the wiki-local `_shared` directory and can import default pages and
files before namespace-local content when requested. Default file XML references binaries
from the `default-images` directory placed next to that XML file.
