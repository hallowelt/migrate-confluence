# Configuration

The migration tool is controlled by two separate input files, both optional:

* a **YAML config file** (`--config`), evaluated by `MigrationConfig`, that
  controls general migration behavior.
* a **CSV wikis-config file** (`--wikis`), evaluated by `WikisConfig`, that
  maps Confluence space keys to target wiki/namespace/root-page settings.

The `--wikis` CSV is read once, during `analyze`, and persisted into the
workspace DB (`wikis_config` table); later steps read it back from there,
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

Wiki title to use as the migration's main page. Confluence space
homepages are matched against this to decide whether a page becomes the
wiki main page or an ordinary page.

### `space-prefix`

* Type: map of `space-key: prefix`
* Default: `{}`

Overrides the namespace prefix used when building attachment/file titles
for a given Confluence space key. The prefix is normally derived from the
space's own namespace mapping (see `WikisConfig`/wikis-config CSV below);
use this option only to override that value for attachment file titles
specifically. A prefix without a trailing colon gets one appended
automatically (e.g. `MYTEST` is treated as `MYTEST:`).

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
Enable this if the target wiki uses the `ExtendedNamespaceFileRepo`
extension (or similar) that expects namespaced file titles.

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

### `composer-skip-namespace`

* Type: list of strings
* Default: `[]`

Wiki namespaces to exclude entirely from the compose step. Use `NS_MAIN`
to skip the main namespace.

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

### `create-sidebar`

* Type: bool
* Default: `true`

Whether to generate a MediaWiki sidebar page from the migrated Confluence
spaces. Set to `false` to skip sidebar generation entirely.

### `profile`

* Type: string
* Default: `bluespice-galaxy`

Selects the output profile, i.e. which converters/composers run,
depending on the feature set of the target wiki. Supported values:

* `bluespice-galaxy` - migrate into a BlueSpice Galaxy instance.
* `mediawiki` - migrate into a stock MediaWiki instance with the
  extension set documented in the project `README.md`.

See `doc/output_profiles.md` for details.

## Wikis-config CSV file (`--wikis`)

The wikis-config CSV maps each Confluence space key to the target wiki's
namespace and root page. It is used by `WikisConfig` to determine, per
space, the wiki namespace prefix, the root page pages get nested under, and
the interwiki prefix used when a space is split into a separate wiki
(`wiki-<wiki-name>`).

Format rules:

* Fields are separated by `;` (semicolon), not comma.
* One row per Confluence space.
* An optional header row starting with `confluence-space-key` is ignored.
* Lines starting with `#`, and empty lines, are ignored (comments).
* A trailing `;` at the end of a line is ignored.
* Columns, in order: `confluence-space-key`, `wiki-name`, `wiki-namespace`,
  `wiki-root-page`.

Example (`doc/interwiki.sample.csv`):

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
spaces mapped to different wikis. Non-alphanumeric characters
(spaces, `:`, `;`, `,`, `#`, `+`, `?`, `*`, `~`, `"`, `'`) are replaced
with `_`.

### `wiki-namespace`

Optional. The MediaWiki namespace pages of this space are migrated into.
If empty, the space key itself is used as namespace. Must not start with
a digit. Same character sanitization as `wiki-name` applies.

### `wiki-root-page`

Optional. A page title pages of this space are nested under as
subpages, e.g. `Marketing` turns `Page A` into
`Marketing/Page A` (within the resolved namespace). If empty, pages are
not nested.
