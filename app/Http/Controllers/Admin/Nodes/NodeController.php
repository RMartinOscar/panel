<?php

namespace App\Http\Controllers\Admin\Nodes;

use App\Http\Controllers\Controller;
use App\Models\Node;
use Illuminate\View\View;
use Spatie\QueryBuilder\QueryBuilder;

class NodeController extends Controller
{
    /**
     * Returns a listing of nodes on the system.
     */
    public function index(): View
    {
        $nodes = QueryBuilder::for(
            Node::query()->withCount('servers')
        )
            ->allowedFilters(['uuid', 'name'])
            ->allowedSorts(['id'])
            ->paginate(25);

        return view('admin.nodes.index', ['nodes' => $nodes]);
    }
}
