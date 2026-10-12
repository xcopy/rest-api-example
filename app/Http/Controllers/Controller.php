<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use DI\Attribute\Inject;
use Laminas\Db\Sql\Sql;

abstract class Controller
{
    #[Inject]
    protected Sql $sql;
}
