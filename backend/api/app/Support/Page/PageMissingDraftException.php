<?php

namespace App\Support\Page;

use RuntimeException;

class PageMissingDraftException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Page is missing a draft version.');
    }
}
