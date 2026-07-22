<?php

namespace App\Presentation\Controllers;

use App\Application\Actions\Inventory\AddProductVariantAction;
use App\Application\Actions\Inventory\CreateProductAction;
use App\Application\Services\BranchAuthorizationService;
use App\Infrastructure\Models\Product;
use App\Infrastructure\Models\ProductVariant;
use App\Infrastructure\Models\User;
use App\Presentation\Requests\ListProductsRequest;
use App\Presentation\Requests\StoreProductRequest;
use App\Presentation\Requests\StoreProductVariantRequest;
use App\Presentation\Resources\ProductResource;
use App\Presentation\Resources\ProductVariantResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ProductController extends Controller
{
    public function index(
        ListProductsRequest $request,
        BranchAuthorizationService $authorization,
    ): AnonymousResourceCollection {
        $data = $request->validated();
        $perPage = isset($data['per_page']) ? (int) $data['per_page'] : 50;
        $lowStock = (bool) ($data['filter']['low_stock'] ?? false);
        $branchId = isset($data['filter']['branch_id']) ? (int) $data['filter']['branch_id'] : null;
        $user = $this->user($request);
        $authorizedBranchIds = ($lowStock || $branchId !== null)
            ? $this->authorizedLowStockBranchIds($authorization, $user, $branchId)
            : null;

        $products = Product::query()
            ->when(isset($data['category_id']), fn (Builder $query) => $query->where('category_id', (int) $data['category_id']))
            ->when($lowStock, function (Builder $query) use ($authorizedBranchIds): void {
                $query->whereHas('variants', function (Builder $variants) use ($authorizedBranchIds): void {
                    $this->constrainLowStockVariants($variants, $authorizedBranchIds);
                });
            })
            ->with([
                'variants' => function ($query) use ($lowStock, $authorizedBranchIds): void {
                    $query->with('attributeValues');

                    if ($lowStock) {
                        $this->constrainLowStockVariants($query, $authorizedBranchIds);
                    }
                },
            ])
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

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, Response::HTTP_UNAUTHORIZED);

        return $user;
    }

    /** @return list<int>|null Null means every tenant branch is authorized. */
    private function authorizedLowStockBranchIds(
        BranchAuthorizationService $authorization,
        User $user,
        ?int $branchId,
    ): ?array {
        $authorizedBranchIds = $authorization->authorizedBranchIds($user, 'stock.adjust');

        abort_unless($authorizedBranchIds !== [], Response::HTTP_FORBIDDEN);

        if ($branchId === null) {
            return $authorizedBranchIds;
        }

        abort_unless(
            $authorizedBranchIds === null || in_array($branchId, $authorizedBranchIds, true),
            Response::HTTP_FORBIDDEN,
        );

        return [$branchId];
    }

    /**
     * @param  Builder<Model>|HasMany<ProductVariant, Product>  $variants
     * @param  list<int>|null  $branchIds
     */
    private function constrainLowStockVariants(Builder|HasMany $variants, ?array $branchIds): void
    {
        $tenantId = current_tenant_id();

        $variants->whereExists(function ($query) use ($branchIds, $tenantId): void {
            $query->selectRaw('1')
                ->from('stock_levels')
                ->where('stock_levels.tenant_id', $tenantId)
                ->whereColumn('stock_levels.product_variant_id', 'product_variants.id')
                ->whereColumn('stock_levels.quantity_on_hand', '<=', 'product_variants.reorder_point')
                ->when($branchIds !== null, fn ($levels) => $levels->whereIn('stock_levels.branch_id', $branchIds));
        });
    }
}
