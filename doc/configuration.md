# Configuration

The migration tool is controlled by two separate optional input files:

* a **YAML config file** (`--config`), evaluated by `MigrationConfig`, that
  controls general migration behavior.
* a **CSV wikis-config file** (`--wikis`), evaluated by `WikisConfig`, that
  maps Confluence space keys to target wiki/namespace/root-page settings.

The `--wikis` CSV is read once, during `analyze`, and persisted into the
workspace DB. Later steps read it back from there,
so `--wikis` does not need to be repeated. The `--config` YAML file is
**not** persisted: it must be passed again with `--config` on every
command (`analyze`, `extract`, `convert`, `compose`) that needs it.

## YAML config file (`--config`)

The file must contain a top-level `config` key holding a map of options. All
options are optional; omitted options fall back to their default.

```yaml
config:
    mainpage: My Main Page
    space-prefix:
        MYSPACE: My_Namespace:
    categories:
        - My Category 1
        - My Category 2
    ext-ns-file-repo-compat: true
    include-history: false
    composer-page-per-xml-limit: 100
    composer-skip-namespace:
        - ABC
    composer-skip-titles:
        - ABC:DEF/GHI
    ns-talk-prefix: Talk
    create-sidebar: true
    profile: mediawiki
```

### `mainpage`

* Type: string
* Default: `Main Page`

Wiki title to use for the target wiki’s main page. This option allows import
into internationalized MediaWiki installations, where the main page is not
called `Main Page`.

**Example:** To migrate into a German-language wiki set the option like this:

```yaml
config:
    mainpage: "Startseite"
```

### `space-prefix`

* Type: map of `space-key: prefix`
* Default: `{}`

If migrating several Confluence spaces into the same wiki this option
configures, which Confluence space is mapped to which MediaWiki namespace.

**Example:** You want to import the spaces `Apple` and `Car`. Their
contents should be placed into the MW namespaces `Fruit:` and `Vehicle:`.
Set the config value like this:

```yaml
config:
    space-prefix:
        Apple: "Fruit"
        Car: "Vehicle"
```

For multi-wiki migrations the prefix is normally derived from the
space's namespace mapping in the wikis-config file and should not be
set here again.

A namespace prefix without a trailing colon gets one appended
automatically (e.g. `Fruit` is treated as `Fruit:`).

### `categories`

* Type: list of strings
* Default: `[]`

Category names that are added to **every** current (i.e. non-historical)
page in addition to the categories derived from Confluence labels.

### `ext-ns-file-repo-compat`

* Type: bool
* Default: `false`

When `true`, restores the namespace prefix (e.g. `MyNamespace:`) on file
titles that would otherwise have it flattened to a dash by filesystem-safe
filename sanitization (`MyNamespace-file.png` -> `MyNamespace:file.png`).
Enable this if the target wiki uses the [`ExtendedNamespaceFileRepo`
extension](https://www.mediawiki.org/wiki/Extension:NSFileRepo) that expects
namespaced file titles.

### `include-history`

* Type: bool
* Default: `false`

When `false`, only the current version of pages, blog posts, space
descriptions, and attachments is migrated; historical versions are
skipped. Set to `true` to migrate full page/attachment history.

### `composer-page-per-xml-limit`

* Type: int
* Default: `0` (no limit, single XML file per output unit)

Maximum number of pages to write into a single composed import XML file
before starting a new one. Use this to split very large migrations into
several smaller XML files.

**Example:** A config setting like this:

```yaml
config:
    composer-page-per-xml-limit: 100
```

for a migration of 300 pages will split the output into three files:

```
pages-000001.xml
pages-000002.xml
pages-000003.xml
```

### `composer-skip-namespace`

* Type: list of strings
* Default: `[]`

Wiki namespaces to exclude entirely from the compose step. Use `NS_MAIN`
to skip the main namespace.

This setting is useful, if you do migrations of spaces in 2 steps while keeping
cross-space links intact. Migrate all spaces together up until the `compose` step,
then ignore all namespaces but one. This allows you to create imports for single
namespaces from Confluence spaces that are migrated in a second step.

### `composer-skip-titles`

* Type: list of strings
* Default: `[]`

Exact, fully-prefixed wiki titles to exclude from the compose step, e.g.
`ABC:DEF/GHI`.

### `ns-talk-prefix`

* Type: string
* Default: `Talk`

Namespace prefix used for the talk/comment pages generated from
Confluence page comments (e.g. `Talk:Page Title`, or
`NamespacePrefix_Talk:Page Title` for pages already in a custom
namespace).

**Deprecated.** This setting will be removed in future versions. We expect the `*_Talk`
namespaces to always be available as aliases, even in non-English wikis.

### `create-sidebar`

* Type: bool
* Default: `true`

Whether to generate a BlueSpice extended sidebar page from the migrated Confluence
spaces. Set to `false` to skip sidebar generation entirely. See
[the BlueSpice documentation](https://en.wiki.bluespice.com/wiki/Manual:Extension/MenuEditor#Enhanced_MediaWiki_sidebar) about this feature.

### `profile`

* Type: string
* Default: `bluespice-galaxy`

Selects the output profile, i.e. which converters/composers run,
depending on the feature set of the target wiki. Supported values:

* `bluespice-galaxy` - migrate into a BlueSpice Galaxy instance.
* `mediawiki` - migrate into a stock MediaWiki instance with the
  extension set documented in the project `README.md`.

See [`doc/output_profiles.md`](./output_profiles.md) for details.

## Wikis-config CSV file (`--wikis`)

The wikis-config CSV maps each Confluence space key to the target wiki's
namespace and root page. It is used to determine, per Confluence
space, the wiki namespace prefix, the root page other pages get nested under, and
the interwiki prefix used when a space is split into a separate wiki
(`wiki-<wiki-name>`).

Format rules:

* Fields are separated by `;` (semicolon).
* One row per Confluence space.
* An optional header row starting with `confluence-space-key` is ignored.
* Lines starting with `#`, and empty lines, are ignored (comments).
* A trailing `;` at the end of a line is ignored.
* Columns, in order: `confluence-space-key`, `wiki-name`, `wiki-namespace`,
  `wiki-root-page`.

Example:

```csv
confluence-space-key;wiki-name;wiki-namespace;wiki-root-page;
PROD;production;;;
MAR;marketing;;Marketing;
ADV;marketing;;Advertising;
EVENT;marketing;Events;;
```

### `confluence-space-key`

Required. The Confluence space key as it appears in the export (e.g.
`PROD`). Must not be empty.

### `wiki-name`

Required. Logical name of the target wiki this space belongs to. Multiple
space keys can share the same `wiki-name` to merge several Confluence
spaces into one target wiki (see `MAR`/`ADV` above, both mapped to
`marketing`). Also used to build the interwiki prefix `wiki-<wiki-name>`
(lower-cased) that is used for interwiki-style page references between
spaces mapped to different wikis.

### `wiki-namespace`

Optional. The MediaWiki namespace pages of this space are migrated into.
If empty, the space key itself is used as namespace. Must not start with
a digit. Same character sanitization as `wiki-name` applies.

### `wiki-root-page`

Optional. A page title pages of this space are nested under as
subpages, e.g. `Marketing` turns `Page A` into
`Marketing/Page A` (within the resolved namespace). If empty, pages are
not nested.
