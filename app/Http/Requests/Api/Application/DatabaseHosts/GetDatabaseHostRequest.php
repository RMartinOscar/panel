<?php

namespace App\Http\Requests\Api\Application\DatabaseHosts;

use App\Http\Requests\Api\Application\ApplicationApiRequest;
use App\Services\Acl\Api\AdminAcl;

class GetDatabaseHostRequest extends ApplicationApiRequest
{
    protected ?string $resource = AdminAcl::RESOURCE_DATABASE_HOSTS;

    protected int $permission = AdminAcl::READ;
}
