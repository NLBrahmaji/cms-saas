<?php

namespace App\Support\Navigation;

use RuntimeException;

class NavigationMissingDraftException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Navigation is missing a draft version.');
    }
}
