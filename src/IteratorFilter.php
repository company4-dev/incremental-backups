<?php
namespace Company4\Incrementor;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveFilterIterator;

class IteratorFilter extends RecursiveFilterIterator
{
    protected $skips;

    public function __construct($recursiveIter, $skips)
    {
        $this->skips = $skips;
        parent::__construct($recursiveIter);
    }

    public function accept(): bool
    {
        foreach ($this->skips as $skip) {
            $result = preg_match('#'.$skip.'#', $this->current()->getPathname());
            if ($result == 1) {
                return false;
            }
        }
        return true;
    }

    public function hasChildren(): bool
    {
        $current = $this->current();

        // symlinked directories aren't descended into by the inner iterator, so check the real path ourselves
        return $current->isDir() || ($current->isLink() && is_dir($current->getRealPath()));
    }

    public function getChildren(): null|self
    {
        $current = $this->current();

        if ($current->isLink()) {
            return new self(new RecursiveDirectoryIterator($current->getRealPath(), FilesystemIterator::SKIP_DOTS), $this->skips);
        }

        return new self($this->getInnerIterator()->getChildren(), $this->skips);
    }
}
