# Incremental Backups
Incremental Backups allows you to reduce the storage taken up by backups by only saving incremental changes. (Full database backups are always captured).

Incremental Backups supports Laravel so your backups can be stored in a sensible location.

On an initial run, the Incrementor will create a full backup. From then on it'll create a full database backup and any files that have been saved since the previous run.

Run another full backup then the incrementor will start with changed from that backup.

Want to just backup the database? Chain in `->database_only()` before `->run()` as explained below. This won't effect any future backups.

## Installation

```bash
composer require company4-dev/incremental-backups
```

## Usage

### Set-up the Incrementor
First we need to tell the incrementor about what to backup and where.

| Parameter    | Type     | Default | Description
| ------------ | -------- | ------- | -----------
| `$directory` | `string` | `''`    | The directory being backed up
| `$target`    | `string` | `'./'`  |  The directory to store the backups. For Laravel, this will store backups within `/storage/$target`.
| `$skips`     | `array`  | `[]`    | An array of additional paths and files to skip. See the Skips section for details of what's excluded by default.

```php
<?php

use Company4\Incrementor\Incrementor;

$incrementor = new Incrementor($directory, $target, []);

```

### Run the backup
Now comes the fun, let's do a backup.

| Parameter         | Type   | Default | Description
| ----------------- | ------ | ------- | -----------
| `$is_incremental` | `bool` | `false` | Whether the backup should be incremental. On the initial run, a full backup will be created regardless what has been passed.

```php
// Run a full backup
$incrementor->run();

// Run an incremental backup
$incrementor->run(true);
```

### Run a Database Only Backup
If you're running an import or deleting a load of data from your app, you'll probably want to backup the database, just in case.
```php
// Note: $is_incremental has no effect, both will run a full database backup.
$incrementor->database_only()->run();
$incrementor->database_only()->run(true);
```

### Deleting old backups
Unfortunately, whilst we've reduced the size of our backups through an incremental strategy, we can't keep backing up as there's only a finite amount of storage available on our servers.

| Parameter | Type  | Default | Description
| --------- | ----- | ------- | -----------
| `$keep`   | `int` | `3`     | The amount of full backups (and their associated incremental backups) to keep.

```php
$incrementor->delete(3);
```

## Default Skip Directories
Regardless of what's passed to the `$skips` attribute when setting up the incrementor the following files will be automatically skipped depending on the platform used.

### Laravel
- bootstrap/cache
- .git
- node_modules
- storage/framework/cache
- storage/framework/views
- tests/.pest
- vendor

### Other Platforms (Including Vanilla PHP)
- .git
- node_modules
- tests/.pest
- vendor
