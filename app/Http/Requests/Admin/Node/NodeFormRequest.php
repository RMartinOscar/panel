<?php

namespace App\Http\Requests\Admin\Node;

use App\Http\Requests\Admin\AdminFormRequest;
use App\Models\Node;

class NodeFormRequest extends AdminFormRequest
{
    /**
     * Get rules to apply to data in this request.
     */
    public function rules(): array
    {
        if ($this->method() === 'PATCH') {
            return Node::getRulesForUpdate($this->route()->parameter('node'));
        }

        return Node::getRules();
    }
}
