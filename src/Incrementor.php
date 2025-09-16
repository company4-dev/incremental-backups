<?php

namespace Company4\Incrementor;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class Incrementor
{
    private $dir;
    private $is_laravel;
    private $skips;
    private $target;

    public function __construct($dir,string $target = './', array $skips = [])
    {
        $skips[]          = $target;
        $this->is_laravel = defined('LARAVEL_START');

        if ($this->is_laravel) {
            if (!Storage::exists($target)) {
                Storage::makeDirectory($target);
            }
        } elseif (!is_dir($target)) {
            mkdir($target, 0775, true);
        }

        if ($this->is_laravel) {
            $skips[] = 'storage/framework';
        }

        $this->dir    = $dir;
        $this->target = $target;
        $this->skips  = $skips;
    }

    public function run(bool $is_incremental = true)
    {
        if (!is_dir($this->dir)) {
            return false;
        }

        $archive           = new ZipArchive();
        $meta_file         = $this->target.'/meta.json';
        $now               = date('Y-m-d_H-i-s');
        $iterator          = new RecursiveDirectoryIterator($this->dir);
        $filter            = new IteratorFilter($iterator, $this->skips);
        $filtered_iterator = new RecursiveIteratorIterator($filter);
        $zip_name          = '';
        $meta              = [
            'full'  => '',
            'files' => [],
        ];

        if ($is_incremental) {
            if ($this->is_laravel) {
                $meta = json_decode(Storage::get($meta_file));
            } elseif (is_file($meta_file)) {
                $meta = json_decode(file_get_contents($meta_file), true);
            }

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
            $zip_name     = $now.'.zip';
            $meta['full'] = $now;
        }

        $target = $this->target.'/'.$zip_name;

        if ($this->is_laravel) {
            $status = $archive->open(Storage::path($target), ZipArchive::CREATE);
        } else {
            $status = $archive->open($target, ZipArchive::CREATE);
        }

        if ($status !== true) {
            return false;
        }

        foreach ($filtered_iterator as $fileInfo) {
            if ($fileInfo->isFile()) {
                $path = str_replace($this->dir.'/', '', $fileInfo->getRealPath());

                if (!array_key_exists($path, $meta['files']) || filemtime($fileInfo->getRealPath()) > $meta['files'][$path]) {
                    $meta['files'][$path] = filemtime($fileInfo->getRealPath());

                    if ($this->is_laravel) {
                        $archive->addFile(base_path($path), $path);
                    } else {
                        $archive->addFile($path, str_replace($this->dir, '', $path));
                    }
                }
            }
        }

        $archive->close();

        $meta = json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($this->is_laravel) {
            Storage::put($meta_file, $meta);
        } else {
            file_put_contents($meta_file, $meta);
        }

        return true;
    }

    public function delete($keep = 3)
    {
        $is_dir = $this->is_laravel ? Storage::exists($this->target) : is_dir($this->target);

        if ($is_dir) {
            if ($this->is_laravel) {
                dd(__LINE__);
            } else {
                $zips = glob($this->target.'/*.zip');
            }

            if ($zips) {
                foreach ($zips as $zip) {
                    if (!str_contains($zip, '___')) {
                        $full[] = $zip;
                    }
                }
            }

            if ($full) {
                ksort($full);

                $deletes = array_diff($full, array_slice($full, -$keep, $keep, true));

                foreach ($deletes as $delete) {
                    if ($incrementals = glob(dirname($delete).'/'.basename($delete, '.zip').'___*.zip')) {
                        foreach ($incrementals as $increment) {
                            unlink($increment);
                        }
                    }
                    unlink($delete);
                }
            }
        }
    }
}
