<?php

namespace Company4\Incrementor;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Spatie\DbDumper\Databases\MySql;
use Throwable;
use ZipArchive;

class Incrementor
{
    private array $skips;
    private bool $database_only = false;
    private bool $is_laravel;
    private string $dir;
    private string $target;

    public function __construct(string $dir = '', string $target = './', array $skips = [])
    {
        $is_laravel = defined('LARAVEL_START');
        $skips[]    = '.git';
        $skips[]    = 'node_modules/';
        $skips[]    = 'tests/.pest';
        $skips[]    = 'vendor/';
        $skips[]    = $target;

        if ($is_laravel) {
            $skips[] = 'bootstrap/cache';
            $skips[] = 'storage/framework/cache';
            $skips[] = 'storage/framework/views';

            $skips = array_merge(
                $skips,
                array_map(
                    fn($path) => str_replace(base_path().'/', '', $path),
                    array_keys(config('filesystems.links'))
                )
            );
        }

        $this->dir        = $dir;
        $this->is_laravel = $is_laravel;
        $this->skips      = $skips;
        $this->target     = $this->is_laravel ? storage_path($target) : $target;

        if (!is_dir($this->target)) {
            mkdir($this->target, 0775, true);
        }
    }

    public function database_only(): self
    {
        $this->database_only = true;

        return $this;
    }

    public function run(bool $incremental = true): bool
    {
        if (!is_dir($this->dir)) {
            return false;
        }

        $archive           = new ZipArchive();
        $database          = DB::connection()->getConfig();
        $meta_file         = $this->target.'/meta.json';
        $now               = date('Y-m-d_H-i-s');
        $iterator          = new RecursiveDirectoryIterator($this->dir, RecursiveDirectoryIterator::SKIP_DOTS);
        $filter            = new IteratorFilter($iterator, $this->skips);
        $filtered_iterator = new RecursiveIteratorIterator($filter);
        $running_tests     = null;
        $zip_name          = '';
        $meta              = [
            'full'  => '',
            'files' => [],
        ];

        try {
            $running_tests = App::runningUnitTests();
        } catch (Throwable $e) {
            $running_tests = false;
        }

        if (!$this->database_only) {
            if ($incremental) {
                $meta = json_decode(file_get_contents($meta_file), true);

                if ($meta['files']) {
                    $zip_name = $meta['full'].'___'.$now;
                } else {
                    $zip_name     = $now;
                    $meta['full'] = $now;
                }

                if ($meta['files']) {
                    $zip_name .= '-incremental';
                }

                $zip_name .= '.zip';
            } else {
                $meta['full'] = $now;
                $zip_name     = $now.'.zip';
            }
        } else {
            $zip_name = $now.'-database.zip';
        }

        $target = $this->target.'/'.$zip_name;

        $status = $archive->open($target, ZipArchive::CREATE);

        if ($status !== true) {
            return false;
        }

        // Backup Database
        $database_file = $this->target.'/'.$database['database'].'.sql';

        if ($running_tests) {
            file_put_contents($database_file, '/* TESTING OUTPUT. */');
        } else {
            MySql::create()
                ->setDbName($database['database'])
                ->setHost($database['host'])
                ->setPassword($database['password'])
                ->setUserName($database['username'])
                ->excludeTables([
                    'pulse_aggregates',
                    'pulse_entries',
                    'pulse_values',
                    'telescope_entries',
                    'telescope_entries_tags',
                    'telescope_monitoring',
                ])
                ->dumpToFile($database_file);
        }

        $archive->addFile($database_file, 'database/'.basename($database_file));

        if (!$this->database_only) {
            foreach ($filtered_iterator as $fileInfo) {
                if ($fileInfo->isFile()) {
                    $path = $fileInfo->getRealPath();

                    if (!array_key_exists($path, $meta['files']) || filemtime($fileInfo->getRealPath()) > $meta['files'][$path]) {
                        $meta['files'][$path] = filemtime($fileInfo->getRealPath());

                        $archive->addFile($path, 'files'.$path);
                    }
                }
            }
        }

        $archive->close();

        if (!$this->database_only) {
            file_put_contents($meta_file, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        unlink($database_file);

        // Create .gitignore
        if (!file_exists($this->target.'/.gitignore')) {
            file_put_contents($this->target.'/.gitignore', "*\n!.gitignore\n");
        }

        return true;
    }

    public function delete(int $keep = 3): int
    {
        $deleted = 0;
        $full    = [];

        if (is_dir($this->target)) {
            $zips = glob($this->target.'/*.zip');

            if ($zips) {
                foreach ($zips as $zip) {
                    $name = basename($zip);

                    if (!str_contains($name, '___') && !str_ends_with($name, '-database.zip')) {
                        $full[] = $zip;
                    }
                }
            }

            if ($full && count($full) > $keep) {
                sort($full);

                $keeps  = array_slice($full, -$keep, $keep);
                // date of the oldest full backup we're keeping; anything older is removed
                $cutoff = substr(basename($keeps[0]), 0, 19);

                foreach ($zips as $zip) {
                    if (substr(basename($zip), 0, 19) < $cutoff) {
                        unlink($zip);
                        $deleted++;
                    }
                }
            }
        }

        return $deleted;
    }
}
