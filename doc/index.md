# Confluence Migration Tool

Welcome to the documentation for this tool! The aim is to convert Confluence spaces into
MediaWiki structures as losslessly as possible.

## Contributing

We welcome any contribution!

This project is actively developed. To prevent frustrating double or stale work,
please open an issue first with the intended changes that you’d like to provide.

## Usage

You need an XML export of one or more of your Confluence spaces to start. [Pandoc](https://pandoc.org/) must be installed on your system.

Migration is then a four-step process:

1. Analyze the import data and read the `entities.xml` file of the Confluence input folder. Store the data into `workspace.sqlite` in the Wiki output folder.
2. Extract data from one place to another, so that the next steps can seemlessly run.
3. Convert the pages one by one from Confluence-flavored HTML to Wiki text. This step runs Pandoc.
4. Compose the converted data into XML files that MediaWiki can easily import.

## Configuration

You can control features of the migration by providing a configuration file. The config
needs to be in YAML format with a top-level `config` key:

```yaml
config:
    space-prefix:
        ABC: "MY_NAMESPACE:ABC/"
        DEF: "MY_NAMESPACE:DEf/"
        GHI: "GHI_NAMESPACE:"
    composer-skip-namespace:
        - ABC
    composer-skip-titles:
        - ABC:DEF/GHI
    composer-page-per-xml-limit: 100,
```

### Fine-Tuning the Output

The migration tool provides a hook system similar to WordPress that allows you to run your
own code at defined places. Create a PHP file, for example named `hooks.php`, alongside your
configuration file:

```php
<?php

return [
    'filters' => [
        'analyze/include_file' => function($value, $file) {
			if ( str_contains( $file->getPathname(), '/some/path/' ) ) {
                // we want to ignore imports from ./input/some/path/entities.xml
                return false;
            }
			return $value;
        },
    ]
]
```

Then reference the file in your configuration:

```yaml
config:
    hook-handler: hooks.php
```

and the hook handler is executed.

See the [list of available hooks](./hooks.md) for details.