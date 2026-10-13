# Fine-Tuning the Output with Hooks

The migration tool provides a hook system that allows you to run your own code
at defined places. Create a PHP file, for example named `hooks.php`, alongside
your configuration file:

```php
<?php

return [
    'filters' => [
        'include-import-file' => function( $value, $file ) {
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

and the hook handler code is executed.

See the [list of available hooks](./hooks.md) for details on hooks.

## Actions and Filters

If you happen to have worked with WordPress’ hook system you might feel at home
instantly. In a nutshell:

* _action_ allow custom code to run at specific points during the execution.
    They can stop the processing, log information, or manipulate some state.
* _filters_ receive a value as first parameter and are expected to return it
    either unchanged or manipulated. The other parameters are helpful context
    for the hook to decide, whether to act, like the page title or the content
    type.

Register the appropriate type in your hooks PHP file.
