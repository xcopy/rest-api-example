<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ConfigService;
use DI\Attribute\Inject;
use Laminas\Db\Sql\Sql;

abstract class Controller
{
    #[Inject]
    protected ConfigService $config;

    #[Inject]
    protected Sql $sql;
}
