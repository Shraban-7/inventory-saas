import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { apiClient } from "@/lib/api-client";
import {
  Product,
  ProductVariant,
  ListProductsParams,
  CreateProductPayload,
  CreateProductVariantPayload,
  PaginatedResponse,
} from "@/types/products";

export const PRODUCT_QUERY_KEYS = {
  all: ["products"] as const,
  list: (params: ListProductsParams) => [...PRODUCT_QUERY_KEYS.all, "list", params] as const,
  variants: (productId: number) => [...PRODUCT_QUERY_KEYS.all, "variants", productId] as const,
  stock: (productId: number) => [...PRODUCT_QUERY_KEYS.all, "stock", productId] as const,
};

/**
 * Fetch paginated list of products. Supports category_id, per_page, page, filter[low_stock], filter[branch_id].
 * IMPORTANT: No search parameter sent to API per backend specification.
 */
export async function fetchProducts(params: ListProductsParams): Promise<PaginatedResponse<Product>> {
  const queryParams: Record<string, string | number | boolean> = {};

  if (params.category_id) queryParams.category_id = params.category_id;
  if (params.per_page) queryParams.per_page = params.per_page;
  if (params.page) queryParams.page = params.page;
  if (params.filter?.low_stock !== undefined) {
    queryParams["filter[low_stock]"] = params.filter.low_stock ? "1" : "0";
  }
  if (params.filter?.branch_id) {
    queryParams["filter[branch_id]"] = params.filter.branch_id;
  }

  const response = await apiClient.get<PaginatedResponse<Product>>("/products", {
    params: queryParams,
  });
  return response.data;
}

/**
 * Create product with initial variants.
 */
export async function createProduct(payload: CreateProductPayload): Promise<Product> {
  const response = await apiClient.post<{ data: Product }>("/products", payload);
  return response.data.data;
}

/**
 * Fetch variants for a specific product.
 */
export async function fetchProductVariants(productId: number): Promise<ProductVariant[]> {
  const response = await apiClient.get<{ data: ProductVariant[] }>(`/products/${productId}/variants`);
  return response.data.data;
}

/**
 * Add a new variant to an existing product.
 */
export async function addProductVariant(
  productId: number,
  payload: CreateProductVariantPayload
): Promise<ProductVariant> {
  const response = await apiClient.post<{ data: ProductVariant }>(`/products/${productId}/variants`, payload);
  return response.data.data;
}

/**
 * Fetch stock levels across branches for a product.
 */
export async function fetchProductStock(productId: number): Promise<Product> {
  const response = await apiClient.get<{ data: Product }>(`/products/${productId}/stock`);
  return response.data.data;
}

// React Query Hooks

export function useProductsQuery(params: ListProductsParams) {
  return useQuery({
    queryKey: PRODUCT_QUERY_KEYS.list(params),
    queryFn: () => fetchProducts(params),
  });
}

export function useProductVariantsQuery(productId: number, enabled = true) {
  return useQuery({
    queryKey: PRODUCT_QUERY_KEYS.variants(productId),
    queryFn: () => fetchProductVariants(productId),
    enabled: enabled && productId > 0,
  });
}

export function useProductStockQuery(productId: number, enabled = true) {
  return useQuery({
    queryKey: PRODUCT_QUERY_KEYS.stock(productId),
    queryFn: () => fetchProductStock(productId),
    enabled: enabled && productId > 0,
  });
}

export function useCreateProductMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: createProduct,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: PRODUCT_QUERY_KEYS.all });
    },
  });
}

export function useAddProductVariantMutation(productId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (payload: CreateProductVariantPayload) => addProductVariant(productId, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: PRODUCT_QUERY_KEYS.all });
    },
  });
}
