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
