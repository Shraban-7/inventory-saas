<?php

namespace App\Presentation\Controllers;

use App\Application\Actions\Sales\CreateCustomerAction;
use App\Application\Actions\Sales\UpdateCustomerAction;
use App\Infrastructure\Models\Customer;
use App\Infrastructure\Models\User;
use App\Presentation\Requests\StoreCustomerRequest;
use App\Presentation\Requests\UpdateCustomerRequest;
use App\Presentation\Resources\CustomerResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class CustomerController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->user($request);
        $perPage = min(max($request->integer('per_page', 50), 1), 100);

        return CustomerResource::collection(
            Customer::query()->latest('id')->paginate($perPage),
        );
    }

    public function store(StoreCustomerRequest $request, CreateCustomerAction $action): JsonResponse
    {
        $this->user($request);
        $customer = $action->handle($request->customerData());

        return (new CustomerResource($customer))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, int $customerId): CustomerResource
    {
        $this->user($request);

        return new CustomerResource(Customer::query()->findOrFail($customerId));
    }

    public function update(UpdateCustomerRequest $request, int $customerId, UpdateCustomerAction $action): CustomerResource
    {
        $this->user($request);
        $customer = Customer::query()->findOrFail($customerId);

        return new CustomerResource($action->handle($customer, $request->customerData()));
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, Response::HTTP_UNAUTHORIZED);

        return $user;
    }
}
