# Tasks (addon for Bear Framework)

A lightweight task scheduling and execution addon for Bear Framework. It lets you register task handlers, enqueue work items with scheduling and priority options, and process them via a runnable worker.

## Snippets

### Define task (register handler)

```php
$app->tasks
    ->define('definition-id', function ($value): void {
        // code
    })
```

### Add task
```php
$app->tasks
    ->add('definition-id', 'example-value', [
        'id' => 'task-id', // optional
        'listID' => '', // empty value for the default listi
        'startTime' => time() + 120, // start in 2 minutes
        'priority' => 3, // 1 - lowest, 5 - highest
        'ignoreIfExists' => true,
    ]);
```

### Run tasks
```php
$app->tasks
    ->addEventListener('runTask', function (\BearFramework\Tasks\RunTaskEventDetails $eventDetails) use ($app) {
        $app->logs->log('run-task', $eventDetails->definitionID);
    })
    ->run([
        'listID' => '',
        'maxExecutionTime' => 30
    ]);
```

## Contributing

Contributions and bug reports are welcome. Open issues or submit pull requests to the repository.

## License

This addon is free to use under the MIT license. See the LICENSE file for details.