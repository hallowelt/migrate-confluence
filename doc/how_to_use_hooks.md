# Fine-Tuning the Output with Hooks

The migration tool provides a hook system that allows you to run your own code
at defined places. Create a PHP file, for example named `hooks.php`, alongside
your configuration file:

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
