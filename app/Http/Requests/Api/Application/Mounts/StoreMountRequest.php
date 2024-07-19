<?php

namespace App\Http\Requests\Api\Application\Mounts;

use App\Http\Requests\Api\Application\ApplicationApiRequest;
use App\Services\Acl\Api\AdminAcl;

class StoreMountRequest extends ApplicationApiRequest
{
    protected ?string $resource = AdminAcl::RESOURCE_MOUNTS;

    protected int $permission = AdminAcl::WRITE;
}
