# Command Architecture

## Overview

This document describes the architecture and structure of the CLI commands in the migrate-confluence tool.

## Commands

The migration tool consists of four main pipeline stages, each implemented as a command:

| Command | Class | Purpose |
|---------|-------|---------|
| `analyze` | [`Analyze`](./Analyze.php) | Reads Confluence XML export and builds SQLite database |
| `extract` | (External) | Extracts migration metadata from the database |
| `convert` | [`Convert`](./Convert.php) | Converts Confluence markup to MediaWiki wikitext |
| `compose` | [`Compose`](./Compose.php) | Aggregates converted content and builds final XML for import |

## Parallel Processing

All three main commands (`Analyze`, `Convert`, `Compose`) support parallel worker processing via the `--workers` option.

### Shared Parallel Logic

The [`ParallelCommandTrait`](./Traits/ParallelCommandTrait.php) provides common functionality for parallel processing:

- **Configuration**: `configureParallelOptions()` - Adds `--workers` and `--worker` CLI options
- **Worker detection**: `isWorkerProcess()` - Checks if running as a spawned worker
- **Execution time**: `preserveExecutionTime()` - Maintains timer across multiple execution phases
- **Logging control**: `shouldSkipExecutionTimeLogging()` - Prevents workers from logging incomplete metrics

### How It Works

**When `--workers > 1` and running as orchestrator:**

1. Command spawns N worker child processes (via `WorkerPool`)
2. Each worker processes a subset of items (round-robin sharded by namespace/space)
3. Workers communicate results back to orchestrator via pipes (file descriptor 3)
4. Orchestrator aggregates results after all workers complete
5. For `Compose`, a final single-threaded "finalize pass" aggregates once-per-wiki artifacts

**Workers and orchestrator features:**

- Only orchestrator writes to the shared SQLite log
- Execution time metrics are aggregated across all workers + orchestrator
- Worker output is aggregated during finalization

## Compose-Specific Features

The [`Compose`](./Compose.php) command has additional logic for handling the finalize pass:

- **`isFinalStep()`** - Checks if running in the finalize-only aggregation phase
- **`setFinalStep(bool)`** - Marks the finalize phase
- **Finalize workflow**:
  - Workers produce per-namespace artifacts only
  - Orchestrator runs a single finalize pass to create once-per-wiki artifacts:
    - `deployment.txt`
    - `wikiimport.sh`
    - Shared content
    - Wiki-level sidebar

## Code Organization

| File | Purpose |
|------|---------|
| [`Analyze.php`](./Analyze.php) | Analyze pipeline command with parallel support |
| [`Convert.php`](./Convert.php) | Convert pipeline command with parallel support |
| [`Compose.php`](./Compose.php) | Compose pipeline command with parallel + finalize support |
| [`Traits/ParallelCommandTrait.php`](./Traits/ParallelCommandTrait.php) | Shared parallel processing logic |
| [`Traits/SetupHooks.php`](./Traits/SetupHooks.php) | Shared custom hook setup |

## Inheritance Hierarchy

Each command extends a different upstream parent class (from the `vendor/hallowelt/mediawiki-lib-migration` library):

```
Analyze extends BatchFileProcessorBase
Convert extends CommandConvert
Compose extends CommandCompose
```

**Why not a single base class?**

PHP doesn't support multiple inheritance. Since each command extends a different parent class with incompatible hierarchies, we use [`ParallelCommandTrait`](./Traits/ParallelCommandTrait.php) as a mixin instead of an abstract base class. This allows all three commands to share parallel processing logic without requiring a common ancestor.

## Adding a New Parallel Command

To add a new command with parallel processing support:

1. Extend the appropriate upstream command class
2. Add `use ParallelCommandTrait;` to your class
3. Call `$this->configureParallelOptions()` in your `configure()` method
4. Implement parallel worker orchestration in your `execute()` method
5. Override `logExecutionTime()` to use `$this->shouldSkipExecutionTimeLogging()`
6. Override `initExecutionTime()` to use `$this->preserveExecutionTime()` if needed

Example:

```php
class MyCommand extends CommandBase {
    use ParallelCommandTrait;
    
    protected function configure(): void {
        parent::configure();
        $this->configureParallelOptions(); // Adds --workers and --worker options
    }
    
    protected function logExecutionTime(): void {
        if ( $this->shouldSkipExecutionTimeLogging() ) {
            return;
        }
        parent::logExecutionTime();
    }
}
```
