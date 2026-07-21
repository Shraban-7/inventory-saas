<?php

namespace App\Presentation\Controllers;

use App\Application\Actions\Purchasing\CreateSupplierAction;
use App\Application\Actions\Purchasing\UpdateSupplierAction;
use App\Infrastructure\Models\Supplier;
use App\Infrastructure\Models\User;
use App\Presentation\Requests\ListSuppliersRequest;
use App\Presentation\Requests\SupplierRequest;
use App\Presentation\Resources\SupplierResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class SupplierController extends Controller
{
    public function index(ListSuppliersRequest $request): AnonymousResourceCollection
    {
        $this->user($request);
        $data = $request->validated();

        return SupplierResource::collection(
            Supplier::query()->latest('id')->paginate(isset($data['per_page']) ? (int) $data['per_page'] : 50),
        );
    }

    public function store(SupplierRequest $request, CreateSupplierAction $action): JsonResponse
    {
        $user = $this->user($request);
        $supplier = $action->handle($request->supplierData(), (int) $user->getKey());

        return (new SupplierResource($supplier))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, int $supplierId): SupplierResource
    {
        $this->user($request);

        return new SupplierResource(Supplier::query()->findOrFail($supplierId));
    }

    public function update(SupplierRequest $request, int $supplierId, UpdateSupplierAction $action): SupplierResource
    {
        $user = $this->user($request);
        Supplier::query()->findOrFail($supplierId);

        return new SupplierResource($action->handle($supplierId, $request->supplierData(), (int) $user->getKey()));
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, Response::HTTP_UNAUTHORIZED);

        return $user;
    }
}
