<?php

namespace App\Presentation\Controllers;

use App\Domain\Repositories\ChartOfAccountTreeRepository;
use App\Presentation\Resources\ChartOfAccountTreeResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ChartOfAccountController extends Controller
{
    public function index(ChartOfAccountTreeRepository $trees): AnonymousResourceCollection
    {
        return ChartOfAccountTreeResource::collection(
            $trees->tree(current_tenant_id()),
        );
    }
}
