export type CostingMethod = "fifo" | "avco";

export interface AttributeValue {
  id: number;
  attribute_id: number;
  value: string;
}

export interface StockLevel {
  id: number;
  product_variant_id: number;
  branch_id: number;
  quantity_on_hand: number;
  branch?: {
    id: number;
    name: string;
    code?: string;
  };
}

export interface ProductVariant {
  id: number;
  sku: string;
  barcode: string | null;
  cost_price: number | string;
  sale_price: number | string;
  reorder_point: number;
  attribute_values?: AttributeValue[];
  stock_levels?: StockLevel[];
}

export interface Product {
  id: number;
  category_id: number;
  name: string;
  description: string | null;
  costing_method: CostingMethod;
  variants?: ProductVariant[];
}

export interface ListProductsParams {
  category_id?: number;
  per_page?: number;
  page?: number;
  filter?: {
    low_stock?: boolean;
    branch_id?: number;
  };
}

export interface CreateProductVariantPayload {
  sku: string;
  barcode?: string;
  cost_price: number;
  sale_price: number;
  reorder_point?: number;
  attribute_value_ids?: number[];
}

export interface CreateProductPayload {
  category_id: number;
  name: string;
  description?: string;
  costing_method?: CostingMethod;
  variants: CreateProductVariantPayload[];
}

export interface PaginatedResponse<T> {
  data: T[];
  links: {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
  };
  meta: {
    current_page: number;
    from: number | null;
    last_page: number;
    per_page: number;
    to: number | null;
    total: number;
  };
}
