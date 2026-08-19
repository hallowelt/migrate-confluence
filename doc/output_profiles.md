# Output Profiles

The migration tool allows to specify which target wiki the content will be migrated
into. This is useful, if the target wikis contain different sets of features, e.g.,
whether Semantic MediaWiki is available or not.

You can configure the output profile in `config.yaml` with the key `profile`:

```yaml
config:
    profile: bluespice-classic
```

Currently the tool supports three output profiles:

* `bluespice-galaxy` (default): migrate into a BlueSpice Galaxy instance
* `bluespice-classic`: migrate into a BlueSpice instance
* `mediawiki`: migrate into a stock MediaWiki instance (still needs some
    extensions to work properly!)
