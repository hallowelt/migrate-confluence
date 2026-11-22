# Apply Page Restrictions

Confluence lets you restrict who may view or edit a page. The tool can carry these restrictions
over to the wiki. It writes them into the import files as a `<pageconstraints>` element for every
page, blog post, comment page (Talk page) and file page.

## Requirements

* The wiki needs the
  [PageConstraints](https://github.com/BlueSpice-Wiki/mediawiki-extensions-PageConstraints) extension.
  Without it, the elements are ignored and every page is open to everyone.
* All users named in a restriction must exist in the wiki. If some are missing, create them and
  import again; newer restrictions replace older ones.
* The feature is on by default. To switch it off, set the config option
  [`add-page-constraints`](./configuration.md#add-page-constraints) to `false`.

## How Confluence Restrictions Are Translated

* A "view" restriction lets only the listed users and groups read the page. Those who are also on the
  "edit" list (or all of them, if there is no edit list) may edit it. The others may only read.
* If there is only an "edit" restriction, everybody may read the page, but only the listed users and
  groups may edit it.
* Comment pages get the restrictions of the page they belong to. File pages get the restrictions of
  the page or blog post the file is attached to. Files that are not attached to anything are
  unrestricted.
* A page that has no restrictions gets an element that says so, so the import can clear old restrictions.

## What to Watch Out For

* In Confluence, a page also inherits the restrictions of its parent pages. The import files are written
  without inheritance, so the tool combines the restrictions of the page and all its parents into one
  list per page. If several levels restrict access, only people who appear on every list remain.
  Confluence group memberships are not part of the export, so with several levels the result can be
  slightly off.
* Someone on the edit list who is not on the view list gets no access, because Confluence does not
  let them view the page either.
* Users who cannot be found in the export are dropped. If nobody is left on a restricted page,
  nobody but administrators can access it. The same applies to a page for which no restriction data
  could be found: it is locked rather than opened.
* All dropped or unknown entries are logged as warnings in the `logging` table of the workspace
  database (step `extract`). Check them after the extract step.
* Not migrated: space permissions, anonymous access and the "share" restriction.

## Mapping Group Names

If group names differ between Confluence and the wiki, pass a CSV file to the `analyze` step:

```
analyze --src=... --dest=... --groupmap=/path/to/groupmap.csv
```

The file has two columns: the Confluence group name and the wiki group name. A header line starting
with `confluence-group` is skipped. Lines starting with `#` are ignored. The delimiter is set by the
[`csv-delimiter`](./configuration.md) config option. The file is read into the workspace database,
so it may be large.

```csv
confluence-group,mediawiki-group
confluence-administrators,sysop
developers,Developers
```

Groups without an entry keep their Confluence name and produce a warning (once per group).
