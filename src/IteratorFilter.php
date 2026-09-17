<?php
namespace Company4\Incrementor;

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

    public function getChildren(): null|self
    {
        return new self($this->getInnerIterator()->getChildren(), $this->skips);
    }
}
