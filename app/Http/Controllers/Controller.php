<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;

abstract class Controller
{
    protected Sql $sql;

    public function __construct(Adapter $db)
    {
        $this->sql = new Sql($db);
    }
}
