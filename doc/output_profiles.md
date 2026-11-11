# Output Profiles

The migration tool allows to specify which target wiki the content will be migrated
into. This is useful, if the target wikis contain different sets of features, e.g.,
whether Semantic MediaWiki is available or not.

You can configure the output profile in `config.yaml` with the key `profile`:

```yaml
config:
    profile: mediawiki
```

Currently the tool supports two output profiles:

* `bluespice-galaxy` (default): migrate into a BlueSpice Galaxy instance
* `mediawiki`: migrate into a stock MediaWiki instance with the set of
    extensions listed in the README.md

## Profile-specific default pages

Default pages (e.g. `Template:Folder`) live in `src/Composer/_defaultpages`
and use Semantic MediaWiki (`{{#ask:`). A file at the same relative path below
`src/Composer/_defaultpages/BlueSpiceGalaxy/` replaces the base file for that
profile. The `bluespice-galaxy` profile replaces the SMW-based `Folder`,
`RecentlyUpdated` and `RecentlyUpdatedRow` pages with DynamicPageList4
(`{{#dpl:`) equivalents.

The macros `children` and `space-details` do not use a
wrapping template in the `bluespice-galaxy` profile. The converter writes the
DynamicPageList4/SubPageList query directly into the page
(`Converter/Processor/BlueSpiceGalaxy`), so options can be edited per page.
Fixing a query later requires a search and replace over the pages.
Macro parameters that cannot be mapped are kept in an HTML comment next to the
query.

Known differences to the SMW version on Galaxy:

* `Folder`: "Recent changes" and "Newest pages" show the latest 5 pages
    without the 30 day window; the "more..." link is gone.
* `space-details`: the "Description" row is omitted (the description is part of
    the space home page).
* `children`: `sort=creation|modified` uses DPL, sorting by title uses SubPageList.
    `depth`, `all`, `style` and `excerpt` are not supported.
