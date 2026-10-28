<?php

namespace IlBronza\Tasks\Facades;

use Illuminate\Support\Facades\Facade;

class Tasks extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'tasks';
    }
}
