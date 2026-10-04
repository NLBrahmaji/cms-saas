<?php

namespace App\Support\Navigation;

use RuntimeException;

class InvalidNavigationVersionItemTreeException extends RuntimeException
{
    public function __construct(string $message = 'Navigation version item tree is invalid.')
    {
        parent::__construct($message);
    }
}
