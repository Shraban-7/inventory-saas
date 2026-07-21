<?php

namespace App\Presentation\Controllers;

use App\Application\Actions\Inventory\AddProductVariantAction;
use App\Application\Actions\Inventory\CreateProductAction;
use App\Infrastructure\Models\Product;
use App\Presentation\Requests\StoreProductRequest;
use App\Presentation\Requests\StoreProductVariantRequest;
use App\Presentation\Resources\ProductResource;
use App\Presentation\Resources\ProductVariantResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(max($request->integer('per_page', 50), 1), 100);
        $products = Product::query()
            ->when($request->integer('category_id'), fn ($query, int $categoryId) => $query->where('category_id', $categoryId))
            ->with('variants.attributeValues')
            ->latest('id')
            ->paginate($perPage);

        return ProductResource::collection($products);
    }

    public function store(StoreProductRequest $request, CreateProductAction $action): JsonResponse
    {
        $product = $action->handle($request->productData());

        return (new ProductResource($product))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function variants(int $productId): AnonymousResourceCollection
    {
        $product = Product::query()->findOrFail($productId);

        return ProductVariantResource::collection($product->variants()->with('attributeValues')->get());
    }

    public function addVariant(int $productId, StoreProductVariantRequest $request, AddProductVariantAction $action): JsonResponse
    {
        $variant = $action->handle(Product::query()->findOrFail($productId), $request->variantData());

        return (new ProductVariantResource($variant))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function stock(int $productId): ProductResource
    {
        $product = Product::query()->with('variants.stockLevels.branch')->findOrFail($productId);

        return new ProductResource($product);
    }
}
