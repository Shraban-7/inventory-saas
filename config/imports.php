<?php

return [
    'disk' => 'local',
    'max_upload_kilobytes' => 10_240,
    'chunk_size' => 100,

    'headers' => [
        'products' => [
            'category_id',
            'name',
            'description',
            'costing_method',
            'sku',
            'barcode',
            'cost_price',
            'sale_price',
            'reorder_point',
        ],
        'stock_adjustments' => [
            'variant_id',
            'branch_id',
            'quantity_delta',
            'type',
            'reason',
            'idempotency_key',
        ],
    ],
];
