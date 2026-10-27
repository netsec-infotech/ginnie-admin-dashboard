<?php

return [
            'seller_dashboard' => [
            'title' => 'labels.seller_tours.seller_dashboard.title',
            'description' => 'labels.seller_tours.seller_dashboard.description',
            'group' => 'labels.seller_tours.groups.overview',
            'permissions' => [
            'dashboard.view',
        ],
            'icon' => 'ti ti-compass',
            'stages' => [
            [
            'route' => 'seller.dashboard',
            'steps' => [
            [
            'selector' => '#revenue-total',
            'title' => 'labels.seller_tours.seller_dashboard.stage_0_step_0.title',
            'description' => 'labels.seller_tours.seller_dashboard.stage_0_step_0.description',
        ],
            [
            'selector' => '#revenue-days',
            'title' => 'labels.seller_tours.seller_dashboard.stage_0_step_1.title',
            'description' => 'labels.seller_tours.seller_dashboard.stage_0_step_1.description',
        ],
            [
            'selector' => '#chart-stores-revenue',
            'title' => 'labels.seller_tours.seller_dashboard.stage_0_step_2.title',
            'description' => 'labels.seller_tours.seller_dashboard.stage_0_step_2.description',
            'optional' => true,
        ],
            [
            'selector' => '#orders-table',
            'title' => 'labels.seller_tours.seller_dashboard.stage_0_step_3.title',
            'description' => 'labels.seller_tours.seller_dashboard.stage_0_step_3.description',
            'optional' => true,
        ],
            [
            'selector' => '#rating-average',
            'title' => 'labels.seller_tours.seller_dashboard.stage_0_step_4.title',
            'description' => 'labels.seller_tours.seller_dashboard.stage_0_step_4.description',
            'optional' => true,
        ],
        ],
        ],
        ],
        ],
            'seller_store_list' => [
            'title' => 'labels.seller_tours.seller_store_list.title',
            'description' => 'labels.seller_tours.seller_store_list.description',
            'group' => 'labels.seller_tours.groups.stores',
            'permissions' => [
            'store.view',
        ],
            'icon' => 'ti ti-compass',
            'stages' => [
            [
            'route' => 'seller.stores.index',
            'steps' => [
            [
            'selector' => '#insight-total-stores',
            'title' => 'labels.seller_tours.seller_store_list.stage_0_step_0.title',
            'description' => 'labels.seller_tours.seller_store_list.stage_0_step_0.description',
        ],
            [
            'selector' => '#verificationStatus',
            'title' => 'labels.seller_tours.seller_store_list.stage_0_step_1.title',
            'description' => 'labels.seller_tours.seller_store_list.stage_0_step_1.description',
        ],
            [
            'selector' => '#visibilityStatus',
            'title' => 'labels.seller_tours.seller_store_list.stage_0_step_2.title',
            'description' => 'labels.seller_tours.seller_store_list.stage_0_step_2.description',
        ],
            [
            'selector' => '#stores-table',
            'title' => 'labels.seller_tours.seller_store_list.stage_0_step_3.title',
            'description' => 'labels.seller_tours.seller_store_list.stage_0_step_3.description',
        ],
            [
            'selector' => '#add-store-btn',
            'title' => 'labels.seller_tours.seller_store_list.stage_0_step_4.title',
            'description' => 'labels.seller_tours.seller_store_list.stage_0_step_4.description',
            'permission' => 'store.create',
            'optional' => true,
            'offer' => 'seller_store_create',
        ],
        ],
        ],
        ],
        ],
            'seller_store_create' => [
            'title' => 'labels.seller_tours.seller_store_create.title',
            'description' => 'labels.seller_tours.seller_store_create.description',
            'group' => 'labels.seller_tours.groups.stores',
            'permissions' => [
            'store.create',
        ],
            'icon' => 'ti ti-compass',
            'stages' => [
            [
            'route' => 'seller.stores.create',
            'steps' => [
            [
            'selector' => '#pills-basic',
            'title' => 'labels.seller_tours.seller_store_create.stage_0_step_0.title',
            'description' => 'labels.seller_tours.seller_store_create.stage_0_step_0.description',
        ],
            [
            'selector' => '#pills-location',
            'title' => 'labels.seller_tours.seller_store_create.stage_0_step_1.title',
            'description' => 'labels.seller_tours.seller_store_create.stage_0_step_1.description',
        ],
            [
            'selector' => '#pills-logos',
            'title' => 'labels.seller_tours.seller_store_create.stage_0_step_2.title',
            'description' => 'labels.seller_tours.seller_store_create.stage_0_step_2.description',
        ],
            [
            'selector' => '#pills-documents',
            'title' => 'labels.seller_tours.seller_store_create.stage_0_step_3.title',
            'description' => 'labels.seller_tours.seller_store_create.stage_0_step_3.description',
        ],
            [
            'selector' => '#pills-bank',
            'title' => 'labels.seller_tours.seller_store_create.stage_0_step_4.title',
            'description' => 'labels.seller_tours.seller_store_create.stage_0_step_4.description',
        ],
            [
            'selector' => '#pills-seo',
            'title' => 'labels.seller_tours.seller_store_create.stage_0_step_5.title',
            'description' => 'labels.seller_tours.seller_store_create.stage_0_step_5.description',
        ],
            [
            'selector' => '[data-seller-tour=\'store-form\'] button[type=\'submit\']',
            'title' => 'labels.seller_tours.seller_store_create.stage_0_step_6.title',
            'description' => 'labels.seller_tours.seller_store_create.stage_0_step_6.description',
        ],
        ],
        ],
        ],
        ],
            'seller_store_edit' => [
            'title' => 'labels.seller_tours.seller_store_edit.title',
            'description' => 'labels.seller_tours.seller_store_edit.description',
            'group' => 'labels.seller_tours.groups.stores',
            'permissions' => [
            'store.view',
            'store.edit',
        ],
            'icon' => 'ti ti-compass',
            'stages' => [
            [
            'route' => 'seller.stores.edit',
            'selection' => 'select_edit',
            'steps' => [
            [
            'selector' => '#pills-basic',
            'title' => 'labels.seller_tours.seller_store_edit.stage_0_step_0.title',
            'description' => 'labels.seller_tours.seller_store_edit.stage_0_step_0.description',
        ],
            [
            'selector' => '#pills-location',
            'title' => 'labels.seller_tours.seller_store_edit.stage_0_step_1.title',
            'description' => 'labels.seller_tours.seller_store_edit.stage_0_step_1.description',
        ],
            [
            'selector' => '#pills-logos',
            'title' => 'labels.seller_tours.seller_store_edit.stage_0_step_2.title',
            'description' => 'labels.seller_tours.seller_store_edit.stage_0_step_2.description',
        ],
            [
            'selector' => '#pills-documents',
            'title' => 'labels.seller_tours.seller_store_edit.stage_0_step_3.title',
            'description' => 'labels.seller_tours.seller_store_edit.stage_0_step_3.description',
        ],
            [
            'selector' => '#pills-bank',
            'title' => 'labels.seller_tours.seller_store_edit.stage_0_step_4.title',
            'description' => 'labels.seller_tours.seller_store_edit.stage_0_step_4.description',
        ],
            [
            'selector' => '#pills-seo',
            'title' => 'labels.seller_tours.seller_store_edit.stage_0_step_5.title',
            'description' => 'labels.seller_tours.seller_store_edit.stage_0_step_5.description',
        ],
            [
            'selector' => '[data-seller-tour=\'store-form\'] button[type=\'submit\']',
            'title' => 'labels.seller_tours.seller_store_edit.stage_0_step_6.title',
            'description' => 'labels.seller_tours.seller_store_edit.stage_0_step_6.description',
        ],
        ],
            'record' => true,
            'listing' => 'seller.stores.index',
        ],
        ],
        ],
            'seller_operating_hours' => [
            'title' => 'labels.seller_tours.seller_operating_hours.title',
            'description' => 'labels.seller_tours.seller_operating_hours.description',
            'group' => 'labels.seller_tours.groups.stores',
            'permissions' => [
            'store.view',
            'store.edit',
        ],
            'icon' => 'ti ti-compass',
            'stages' => [
            [
            'route' => 'seller.stores.configuration',
            'selection' => 'select_configuration',
            'steps' => [
            [
            'selector' => '#pills-scheduling',
            'title' => 'labels.seller_tours.seller_operating_hours.stage_0_step_0.title',
            'description' => 'labels.seller_tours.seller_operating_hours.stage_0_step_0.description',
        ],
            [
            'selector' => '#accordion-time-slot-config',
            'title' => 'labels.seller_tours.seller_operating_hours.stage_0_step_1.title',
            'description' => 'labels.seller_tours.seller_operating_hours.stage_0_step_1.description',
        ],
            [
            'selector' => '#accordion-time-slot-config .day-switch',
            'title' => 'labels.seller_tours.seller_operating_hours.stage_0_step_2.title',
            'description' => 'labels.seller_tours.seller_operating_hours.stage_0_step_2.description',
        ],
            [
            'selector' => '#accordion-time-slot-config .break-switch',
            'title' => 'labels.seller_tours.seller_operating_hours.stage_0_step_3.title',
            'description' => 'labels.seller_tours.seller_operating_hours.stage_0_step_3.description',
            'optional' => true,
        ],
            [
            'selector' => '[name=\'order_preparation_time\']',
            'title' => 'labels.seller_tours.seller_operating_hours.stage_0_step_4.title',
            'description' => 'labels.seller_tours.seller_operating_hours.stage_0_step_4.description',
        ],
            [
            'selector' => '#pills-scheduling [name=\'status\']',
            'title' => 'labels.seller_tours.seller_operating_hours.stage_0_step_5.title',
            'description' => 'labels.seller_tours.seller_operating_hours.stage_0_step_5.description',
        ],
            [
            'selector' => '[data-seller-tour=\'store-configuration\'] button[type=\'submit\']',
            'title' => 'labels.seller_tours.seller_operating_hours.stage_0_step_6.title',
            'description' => 'labels.seller_tours.seller_operating_hours.stage_0_step_6.description',
        ],
        ],
            'record' => true,
            'listing' => 'seller.stores.index',
        ],
        ],
        ],
            'seller_store_configuration' => [
            'title' => 'labels.seller_tours.seller_store_configuration.title',
            'description' => 'labels.seller_tours.seller_store_configuration.description',
            'group' => 'labels.seller_tours.groups.stores',
            'permissions' => [
            'store.view',
            'store.edit',
        ],
            'icon' => 'ti ti-compass',
            'stages' => [
            [
            'route' => 'seller.stores.configuration',
            'selection' => 'select_configuration',
            'steps' => [
            [
            'selector' => '#pills-scheduled-delivery',
            'title' => 'labels.seller_tours.seller_store_configuration.stage_0_step_0.title',
            'description' => 'labels.seller_tours.seller_store_configuration.stage_0_step_0.description',
            'optional' => true,
            'feature' => 'scheduled',
        ],
            [
            'selector' => '#pills-pickup-cod',
            'title' => 'labels.seller_tours.seller_store_configuration.stage_0_step_1.title',
            'description' => 'labels.seller_tours.seller_store_configuration.stage_0_step_1.description',
            'optional' => true,
        ],
            [
            'selector' => '#pills-store',
            'title' => 'labels.seller_tours.seller_store_configuration.stage_0_step_2.title',
            'description' => 'labels.seller_tours.seller_store_configuration.stage_0_step_2.description',
        ],
            [
            'selector' => '#pills-policies',
            'title' => 'labels.seller_tours.seller_store_configuration.stage_0_step_3.title',
            'description' => 'labels.seller_tours.seller_store_configuration.stage_0_step_3.description',
        ],
            [
            'selector' => '#pills-metadata',
            'title' => 'labels.seller_tours.seller_store_configuration.stage_0_step_4.title',
            'description' => 'labels.seller_tours.seller_store_configuration.stage_0_step_4.description',
            'optional' => true,
        ],
            [
            'selector' => '#pills-pos',
            'title' => 'labels.seller_tours.seller_store_configuration.stage_0_step_5.title',
            'description' => 'labels.seller_tours.seller_store_configuration.stage_0_step_5.description',
            'feature' => 'pos',
            'optional' => true,
        ],
            [
            'selector' => '[data-seller-tour=\'store-configuration\'] button[type=\'submit\']',
            'title' => 'labels.seller_tours.seller_store_configuration.stage_0_step_6.title',
            'description' => 'labels.seller_tours.seller_store_configuration.stage_0_step_6.description',
        ],
        ],
            'record' => true,
            'listing' => 'seller.stores.index',
        ],
        ],
        ],
            'seller_orders' => [
            'title' => 'labels.seller_tours.seller_orders.title',
            'description' => 'labels.seller_tours.seller_orders.description',
            'group' => 'labels.seller_tours.groups.orders',
            'permissions' => [
            'order.view',
        ],
            'icon' => 'ti ti-compass',
            'stages' => [
            [
            'route' => 'seller.orders.index',
            'steps' => [
            [
            'selector' => '#insight-total-orders',
            'title' => 'labels.seller_tours.seller_orders.stage_0_step_0.title',
            'description' => 'labels.seller_tours.seller_orders.stage_0_step_0.description',
        ],
            [
            'selector' => '#storeFilter',
            'title' => 'labels.seller_tours.seller_orders.stage_0_step_1.title',
            'description' => 'labels.seller_tours.seller_orders.stage_0_step_1.description',
        ],
            [
            'selector' => '#statusFilter',
            'title' => 'labels.seller_tours.seller_orders.stage_0_step_2.title',
            'description' => 'labels.seller_tours.seller_orders.stage_0_step_2.description',
        ],
            [
            'selector' => '#rangeFilter',
            'title' => 'labels.seller_tours.seller_orders.stage_0_step_3.title',
            'description' => 'labels.seller_tours.seller_orders.stage_0_step_3.description',
        ],
            [
            'selector' => '#orders-list-table',
            'title' => 'labels.seller_tours.seller_orders.stage_0_step_4.title',
            'description' => 'labels.seller_tours.seller_orders.stage_0_step_4.description',
        ],
            [
            'selector' => '#orders-table',
            'title' => 'labels.seller_tours.seller_orders.stage_0_step_5.title',
            'description' => 'labels.seller_tours.seller_orders.stage_0_step_5.description',
            'optional' => true,
        ],
        ],
        ],
        ],
        ],
            'seller_order_details' => [
            'title' => 'labels.seller_tours.seller_order_details.title',
            'description' => 'labels.seller_tours.seller_order_details.description',
            'group' => 'labels.seller_tours.groups.orders',
            'permissions' => [
            'order.view',
        ],
            'icon' => 'ti ti-compass',
            'stages' => [
            [
            'route' => 'seller.orders.show',
            'steps' => [
            [
            'selector' => '[data-seller-tour=\'order-details\']',
            'title' => 'labels.seller_tours.seller_order_details.stage_0_step_0.title',
            'description' => 'labels.seller_tours.seller_order_details.stage_0_step_0.description',
        ],
            [
            'selector' => '#sellerOrderActions',
            'title' => 'labels.seller_tours.seller_order_details.stage_0_step_1.title',
            'description' => 'labels.seller_tours.seller_order_details.stage_0_step_1.description',
            'permission' => 'order.update_status',
            'optional' => true,
        ],
            [
            'selector' => '#sellerSelectAllItems',
            'title' => 'labels.seller_tours.seller_order_details.stage_0_step_2.title',
            'description' => 'labels.seller_tours.seller_order_details.stage_0_step_2.description',
            'optional' => true,
        ],
            [
            'selector' => '#sellerBulkUpdateBtn',
            'title' => 'labels.seller_tours.seller_order_details.stage_0_step_3.title',
            'description' => 'labels.seller_tours.seller_order_details.stage_0_step_3.description',
            'permission' => 'order.update_status',
            'optional' => true,
        ],
            [
            'selector' => '#btnVerifyPickupOtp',
            'title' => 'labels.seller_tours.seller_order_details.stage_0_step_4.title',
            'description' => 'labels.seller_tours.seller_order_details.stage_0_step_4.description',
            'permission' => 'pickup.manage',
            'optional' => true,
        ],
            [
            'selector' => '#refundsAccordion',
            'title' => 'labels.seller_tours.seller_order_details.stage_0_step_5.title',
            'description' => 'labels.seller_tours.seller_order_details.stage_0_step_5.description',
            'optional' => true,
        ],
        ],
            'record' => true,
            'listing' => 'seller.orders.index',
        ],
        ],
        ],
            'seller_scheduled_orders' => [
            'title' => 'labels.seller_tours.seller_scheduled_orders.title',
            'description' => 'labels.seller_tours.seller_scheduled_orders.description',
            'group' => 'labels.seller_tours.groups.orders',
            'permissions' => [
            'order.view',
        ],
            'icon' => 'ti ti-compass',
            'stages' => [
            [
            'route' => 'seller.scheduled_orders.index',
            'steps' => [
            [
            'selector' => '#orderTypeFilter',
            'title' => 'labels.seller_tours.seller_scheduled_orders.stage_0_step_0.title',
            'description' => 'labels.seller_tours.seller_scheduled_orders.stage_0_step_0.description',
        ],
            [
            'selector' => '#storeFilter',
            'title' => 'labels.seller_tours.seller_scheduled_orders.stage_0_step_1.title',
            'description' => 'labels.seller_tours.seller_scheduled_orders.stage_0_step_1.description',
        ],
            [
            'selector' => '#statusFilter',
            'title' => 'labels.seller_tours.seller_scheduled_orders.stage_0_step_2.title',
            'description' => 'labels.seller_tours.seller_scheduled_orders.stage_0_step_2.description',
        ],
            [
            'selector' => '#rangeFilter',
            'title' => 'labels.seller_tours.seller_scheduled_orders.stage_0_step_3.title',
            'description' => 'labels.seller_tours.seller_scheduled_orders.stage_0_step_3.description',
        ],
            [
            'selector' => '#orders-list-table',
            'title' => 'labels.seller_tours.seller_scheduled_orders.stage_0_step_4.title',
            'description' => 'labels.seller_tours.seller_scheduled_orders.stage_0_step_4.description',
        ],
            [
            'selector' => '#orders-table',
            'title' => 'labels.seller_tours.seller_scheduled_orders.stage_0_step_5.title',
            'description' => 'labels.seller_tours.seller_scheduled_orders.stage_0_step_5.description',
            'optional' => true,
        ],
        ],
        ],
        ],
            'feature' => 'scheduled',
        ],
            'seller_returns' => [
            'title' => 'labels.seller_tours.seller_returns.title',
            'description' => 'labels.seller_tours.seller_returns.description',
            'group' => 'labels.seller_tours.groups.orders',
            'permissions' => [
            'return.view',
        ],
            'icon' => 'ti ti-compass',
            'stages' => [
            [
            'route' => 'seller.returns.index',
            'steps' => [
            [
            'selector' => '#insight-total-return-requests',
            'title' => 'labels.seller_tours.seller_returns.stage_0_step_0.title',
            'description' => 'labels.seller_tours.seller_returns.stage_0_step_0.description',
        ],
            [
            'selector' => '#returns-filters',
            'title' => 'labels.seller_tours.seller_returns.stage_0_step_1.title',
            'description' => 'labels.seller_tours.seller_returns.stage_0_step_1.description',
        ],
            [
            'selector' => '#returns-table',
            'title' => 'labels.seller_tours.seller_returns.stage_0_step_2.title',
            'description' => 'labels.seller_tours.seller_returns.stage_0_step_2.description',
        ],
            [
            'selector' => '#returns-table_wrapper .dt-search, #returns-table_wrapper .dataTables_filter',
            'title' => 'labels.seller_tours.seller_returns.stage_0_step_3.title',
            'description' => 'labels.seller_tours.seller_returns.stage_0_step_3.description',
            'optional' => true,
        ],
            [
            'selector' => '#returns-table_wrapper .dt-length, #returns-table_wrapper .dataTables_length',
            'title' => 'labels.seller_tours.seller_returns.stage_0_step_4.title',
            'description' => 'labels.seller_tours.seller_returns.stage_0_step_4.description',
            'optional' => true,
        ],
            [
            'selector' => '#returns-table_wrapper .dt-paging, #returns-table_wrapper .dataTables_paginate',
            'title' => 'labels.seller_tours.seller_returns.stage_0_step_5.title',
            'description' => 'labels.seller_tours.seller_returns.stage_0_step_5.description',
            'optional' => true,
        ],
        ],
        ],
            [
            'route' => 'seller.returns.show',
            'steps' => [
            [
            'selector' => '[data-seller-tour=\'return-details\']',
            'title' => 'labels.seller_tours.seller_returns.stage_1_step_0.title',
            'description' => 'labels.seller_tours.seller_returns.stage_1_step_0.description',
        ],
            [
            'selector' => '[data-seller-tour=\'return-pipeline\']',
            'title' => 'labels.seller_tours.seller_returns.stage_1_step_1.title',
            'description' => 'labels.seller_tours.seller_returns.stage_1_step_1.description',
        ],
            [
            'selector' => '[data-bs-target=\'#acceptModel\']',
            'title' => 'labels.seller_tours.seller_returns.stage_1_step_2.title',
            'description' => 'labels.seller_tours.seller_returns.stage_1_step_2.description',
            'permission' => 'return.decide',
            'optional' => true,
        ],
            [
            'selector' => '[data-bs-target=\'#rejectModel\']',
            'title' => 'labels.seller_tours.seller_returns.stage_1_step_3.title',
            'description' => 'labels.seller_tours.seller_returns.stage_1_step_3.description',
            'permission' => 'return.decide',
            'optional' => true,
        ],
            [
            'selector' => '[data-seller-tour=\'return-refund\']',
            'title' => 'labels.seller_tours.seller_returns.stage_1_step_4.title',
            'description' => 'labels.seller_tours.seller_returns.stage_1_step_4.description',
            'optional' => true,
        ],
        ],
            'record' => true,
            'listing' => 'seller.returns.index',
        ],
        ],
        ],
            'seller_return_receipt' => [
            'title' => 'labels.seller_tours.seller_return_receipt.title',
            'description' => 'labels.seller_tours.seller_return_receipt.description',
            'group' => 'labels.seller_tours.groups.orders',
            'permissions' => [
            'return.view',
        ],
            'icon' => 'ti ti-compass',
            'stages' => [
            [
            'route' => 'seller.returns.show',
            'steps' => [
            [
            'selector' => '[data-seller-tour=\'return-details\']',
            'title' => 'labels.seller_tours.seller_return_receipt.stage_0_step_0.title',
            'description' => 'labels.seller_tours.seller_return_receipt.stage_0_step_0.description',
        ],
            [
            'selector' => '[data-seller-tour=\'return-pipeline\']',
            'title' => 'labels.seller_tours.seller_return_receipt.stage_0_step_1.title',
            'description' => 'labels.seller_tours.seller_return_receipt.stage_0_step_1.description',
        ],
            [
            'selector' => '[data-bs-target=\'#storeConfirmModel\']',
            'title' => 'labels.seller_tours.seller_return_receipt.stage_0_step_2.title',
            'description' => 'labels.seller_tours.seller_return_receipt.stage_0_step_2.description',
            'permission' => 'return.decide',
            'optional' => true,
        ],
            [
            'selector' => '[data-bs-target=\'#storeEscalateModel\']',
            'title' => 'labels.seller_tours.seller_return_receipt.stage_0_step_3.title',
            'description' => 'labels.seller_tours.seller_return_receipt.stage_0_step_3.description',
            'permission' => 'return.decide',
            'optional' => true,
        ],
            [
            'selector' => '[data-seller-tour=\'return-refund\']',
            'title' => 'labels.seller_tours.seller_return_receipt.stage_0_step_4.title',
            'description' => 'labels.seller_tours.seller_return_receipt.stage_0_step_4.description',
            'optional' => true,
        ],
            [
            'selector' => '[data-seller-tour=\'return-timeline\']',
            'title' => 'labels.seller_tours.seller_return_receipt.stage_0_step_5.title',
            'description' => 'labels.seller_tours.seller_return_receipt.stage_0_step_5.description',
            'optional' => true,
        ],
        ],
            'record' => true,
            'listing' => 'seller.returns.index',
        ],
        ],
        ],
            'seller_return_reasons' => [
            'title' => 'labels.seller_tours.seller_return_reasons.title',
            'description' => 'labels.seller_tours.seller_return_reasons.description',
            'group' => 'labels.seller_tours.groups.orders',
            'permissions' => [
            'return.view',
        ],
            'icon' => 'ti ti-compass',
            'stages' => [
            [
            'route' => 'seller.return-reasons.index',
            'steps' => [
            [
            'selector' => '#seller-reasons-table',
            'title' => 'labels.seller_tours.seller_return_reasons.stage_0_step_0.title',
            'description' => 'labels.seller_tours.seller_return_reasons.stage_0_step_0.description',
        ],
            [
            'selector' => '#seller-reasons-table_wrapper .dt-search, #seller-reasons-table_wrapper .dataTables_filter',
            'title' => 'labels.seller_tours.seller_return_reasons.stage_0_step_1.title',
            'description' => 'labels.seller_tours.seller_return_reasons.stage_0_step_1.description',
            'optional' => true,
        ],
            [
            'selector' => '#seller-reasons-table_wrapper .dt-length, #seller-reasons-table_wrapper .dataTables_length',
            'title' => 'labels.seller_tours.seller_return_reasons.stage_0_step_2.title',
            'description' => 'labels.seller_tours.seller_return_reasons.stage_0_step_2.description',
            'optional' => true,
        ],
            [
            'selector' => '#seller-reasons-table_wrapper .dt-paging, #seller-reasons-table_wrapper .dataTables_paginate',
            'title' => 'labels.seller_tours.seller_return_reasons.stage_0_step_3.title',
            'description' => 'labels.seller_tours.seller_return_reasons.stage_0_step_3.description',
            'optional' => true,
        ],
            [
            'selector' => '#seller-return-reason-modal #seller_title',
            'title' => 'labels.seller_tours.seller_return_reasons.stage_0_step_4.title',
            'description' => 'labels.seller_tours.seller_return_reasons.stage_0_step_4.description',
            'permission' => 'return.decide',
            'optional' => true,
            'reveal' => [
            'type' => 'modal',
            'selector' => '#seller-return-reason-modal',
        ],
        ],
            [
            'selector' => '#seller-return-reason-modal #product_ids',
            'title' => 'labels.seller_tours.seller_return_reasons.stage_0_step_5.title',
            'description' => 'labels.seller_tours.seller_return_reasons.stage_0_step_5.description',
            'permission' => 'return.decide',
            'optional' => true,
        ],
            [
            'selector' => '#seller_otp_required',
            'title' => 'labels.seller_tours.seller_return_reasons.stage_0_step_6.title',
            'description' => 'labels.seller_tours.seller_return_reasons.stage_0_step_6.description',
            'permission' => 'return.decide',
            'optional' => true,
        ],
        ],
        ],
        ],
        ],
            'seller_pos_checkout' => [
            'title' => 'labels.seller_tours.seller_pos_checkout.title',
            'description' => 'labels.seller_tours.seller_pos_checkout.description',
            'group' => 'labels.seller_tours.groups.pos',
            'permissions' => [
            'pos.view',
        ],
            'icon' => 'ti ti-compass',
            'stages' => [
            [
            'route' => 'seller.pos.index',
            'steps' => [
            [
            'selector' => '#pos-store-select',
            'title' => 'labels.seller_tours.seller_pos_checkout.stage_0_step_0.title',
            'description' => 'labels.seller_tours.seller_pos_checkout.stage_0_step_0.description',
        ],
            [
            'selector' => '#pos-search-input',
            'title' => 'labels.seller_tours.seller_pos_checkout.stage_0_step_1.title',
            'description' => 'labels.seller_tours.seller_pos_checkout.stage_0_step_1.description',
        ],
            [
            'selector' => '#pos-barcode-input',
            'title' => 'labels.seller_tours.seller_pos_checkout.stage_0_step_2.title',
            'description' => 'labels.seller_tours.seller_pos_checkout.stage_0_step_2.description',
        ],
            [
            'selector' => '#pos-product-grid',
            'title' => 'labels.seller_tours.seller_pos_checkout.stage_0_step_3.title',
            'description' => 'labels.seller_tours.seller_pos_checkout.stage_0_step_3.description',
        ],
            [
            'selector' => '#pos-cart-list, #pos-cart-empty',
            'title' => 'labels.seller_tours.seller_pos_checkout.stage_0_step_4.title',
            'description' => 'labels.seller_tours.seller_pos_checkout.stage_0_step_4.description',
        ],
            [
            'selector' => '#pos-cart-customer-btn',
            'title' => 'labels.seller_tours.seller_pos_checkout.stage_0_step_5.title',
            'description' => 'labels.seller_tours.seller_pos_checkout.stage_0_step_5.description',
            'permission' => 'pos.create_order',
            'optional' => true,
        ],
            [
            'selector' => '#pos-checkout-btn',
            'title' => 'labels.seller_tours.seller_pos_checkout.stage_0_step_6.title',
            'description' => 'labels.seller_tours.seller_pos_checkout.stage_0_step_6.description',
            'permission' => 'pos.create_order',
            'optional' => true,
        ],
        ],
        ],
        ],
            'feature' => 'pos',
        ],
            'seller_pos_parked' => [
            'title' => 'labels.seller_tours.seller_pos_parked.title',
            'description' => 'labels.seller_tours.seller_pos_parked.description',
            'group' => 'labels.seller_tours.groups.pos',
            'permissions' => [
            'pos.view',
            'pos.park_sale',
        ],
            'icon' => 'ti ti-compass',
            'stages' => [
            [
            'route' => 'seller.pos.index',
            'steps' => [
            [
            'selector' => '#pos-store-select',
            'title' => 'labels.seller_tours.seller_pos_parked.stage_0_step_0.title',
            'description' => 'labels.seller_tours.seller_pos_parked.stage_0_step_0.description',
        ],
            [
            'selector' => '#pos-hold-btn',
            'title' => 'labels.seller_tours.seller_pos_parked.stage_0_step_1.title',
            'description' => 'labels.seller_tours.seller_pos_parked.stage_0_step_1.description',
        ],
            [
            'selector' => '#pos-held-btn',
            'title' => 'labels.seller_tours.seller_pos_parked.stage_0_step_2.title',
            'description' => 'labels.seller_tours.seller_pos_parked.stage_0_step_2.description',
        ],
            [
            'selector' => '#pos-held-count',
            'title' => 'labels.seller_tours.seller_pos_parked.stage_0_step_3.title',
            'description' => 'labels.seller_tours.seller_pos_parked.stage_0_step_3.description',
        ],
            [
            'selector' => '#pos-held-modal',
            'title' => 'labels.seller_tours.seller_pos_parked.stage_0_step_4.title',
            'description' => 'labels.seller_tours.seller_pos_parked.stage_0_step_4.description',
            'optional' => true,
        ],
        ],
        ],
        ],
            'feature' => 'pos',
        ],
            'seller_pos_payments' => [
            'title' => 'labels.seller_tours.seller_pos_payments.title',
            'description' => 'labels.seller_tours.seller_pos_payments.description',
            'group' => 'labels.seller_tours.groups.pos',
            'permissions' => [
            'pos.view',
        ],
            'icon' => 'ti ti-compass',
            'stages' => [
            [
            'route' => 'seller.pos.index',
            'steps' => [
            [
            'selector' => '#pos-store-select',
            'title' => 'labels.seller_tours.seller_pos_payments.stage_0_step_0.title',
            'description' => 'labels.seller_tours.seller_pos_payments.stage_0_step_0.description',
        ],
            [
            'selector' => '#pos-cart-total',
            'title' => 'labels.seller_tours.seller_pos_payments.stage_0_step_1.title',
            'description' => 'labels.seller_tours.seller_pos_payments.stage_0_step_1.description',
        ],
            [
            'selector' => '#pos-checkout-btn',
            'title' => 'labels.seller_tours.seller_pos_payments.stage_0_step_2.title',
            'description' => 'labels.seller_tours.seller_pos_payments.stage_0_step_2.description',
            'optional' => true,
        ],
            [
            'selector' => '#pos-payment-tabs',
            'title' => 'labels.seller_tours.seller_pos_payments.stage_0_step_3.title',
            'description' => 'labels.seller_tours.seller_pos_payments.stage_0_step_3.description',
            'permission' => 'pos.create_order',
            'optional' => true,
        ],
            [
            'selector' => '#pos-complete-sale-btn',
            'title' => 'labels.seller_tours.seller_pos_payments.stage_0_step_4.title',
            'description' => 'labels.seller_tours.seller_pos_payments.stage_0_step_4.description',
            'permission' => 'pos.create_order',
            'optional' => true,
        ],
            [
            'selector' => '#pos-recent-btn',
            'title' => 'labels.seller_tours.seller_pos_payments.stage_0_step_5.title',
            'description' => 'labels.seller_tours.seller_pos_payments.stage_0_step_5.description',
        ],
        ],
        ],
        ],
            'feature' => 'pos',
        ],
            'seller_pos_refunds' => [
            'title' => 'labels.seller_tours.seller_pos_refunds.title',
            'description' => 'labels.seller_tours.seller_pos_refunds.description',
            'group' => 'labels.seller_tours.groups.pos',
            'permissions' => [
            'pos.view',
            'pos.refund',
        ],
            'icon' => 'ti ti-compass',
            'stages' => [
            [
            'route' => 'seller.pos.index',
            'steps' => [
            [
            'selector' => '#pos-recent-btn',
            'title' => 'labels.seller_tours.seller_pos_refunds.stage_0_step_0.title',
            'description' => 'labels.seller_tours.seller_pos_refunds.stage_0_step_0.description',
        ],
            [
            'selector' => '#pos-recent-modal',
            'title' => 'labels.seller_tours.seller_pos_refunds.stage_0_step_1.title',
            'description' => 'labels.seller_tours.seller_pos_refunds.stage_0_step_1.description',
            'optional' => true,
        ],
            [
            'selector' => '#pos-refund-rows',
            'title' => 'labels.seller_tours.seller_pos_refunds.stage_0_step_2.title',
            'description' => 'labels.seller_tours.seller_pos_refunds.stage_0_step_2.description',
            'optional' => true,
        ],
            [
            'selector' => '#pos-refund-reason',
            'title' => 'labels.seller_tours.seller_pos_refunds.stage_0_step_3.title',
            'description' => 'labels.seller_tours.seller_pos_refunds.stage_0_step_3.description',
            'optional' => true,
        ],
            [
            'selector' => '#pos-refund-total',
            'title' => 'labels.seller_tours.seller_pos_refunds.stage_0_step_4.title',
            'description' => 'labels.seller_tours.seller_pos_refunds.stage_0_step_4.description',
            'optional' => true,
        ],
            [
            'selector' => '#pos-refund-submit',
            'title' => 'labels.seller_tours.seller_pos_refunds.stage_0_step_5.title',
            'description' => 'labels.seller_tours.seller_pos_refunds.stage_0_step_5.description',
            'optional' => true,
        ],
        ],
            'requireExisting' => '#pos-refund-modal.show',
            'selection' => 'pos_refund',
        ],
        ],
            'feature' => 'pos',
        ],
            'seller_pos_dashboard' => [
            'title' => 'labels.seller_tours.seller_pos_dashboard.title',
            'description' => 'labels.seller_tours.seller_pos_dashboard.description',
            'group' => 'labels.seller_tours.groups.pos',
            'permissions' => [
            'pos.view',
        ],
            'icon' => 'ti ti-compass',
            'stages' => [
            [
            'route' => 'seller.pos.dashboard',
            'steps' => [
            [
            'selector' => '#posStoreFilter',
            'title' => 'labels.seller_tours.seller_pos_dashboard.stage_0_step_0.title',
            'description' => 'labels.seller_tours.seller_pos_dashboard.stage_0_step_0.description',
        ],
            [
            'selector' => '#posRangeFilter',
            'title' => 'labels.seller_tours.seller_pos_dashboard.stage_0_step_1.title',
            'description' => 'labels.seller_tours.seller_pos_dashboard.stage_0_step_1.description',
        ],
            [
            'selector' => '#kpiRevenue',
            'title' => 'labels.seller_tours.seller_pos_dashboard.stage_0_step_2.title',
            'description' => 'labels.seller_tours.seller_pos_dashboard.stage_0_step_2.description',
        ],
            [
            'selector' => '#chart-pos-sales-trend',
            'title' => 'labels.seller_tours.seller_pos_dashboard.stage_0_step_3.title',
            'description' => 'labels.seller_tours.seller_pos_dashboard.stage_0_step_3.description',
        ],
            [
            'selector' => '#chart-pos-payment-donut',
            'title' => 'labels.seller_tours.seller_pos_dashboard.stage_0_step_4.title',
            'description' => 'labels.seller_tours.seller_pos_dashboard.stage_0_step_4.description',
        ],
            [
            'selector' => '#topProductsTable',
            'title' => 'labels.seller_tours.seller_pos_dashboard.stage_0_step_5.title',
            'description' => 'labels.seller_tours.seller_pos_dashboard.stage_0_step_5.description',
        ],
            [
            'selector' => '#lowStockTable',
            'title' => 'labels.seller_tours.seller_pos_dashboard.stage_0_step_6.title',
            'description' => 'labels.seller_tours.seller_pos_dashboard.stage_0_step_6.description',
        ],
        ],
        ],
        ],
            'feature' => 'pos',
        ],
            'seller_product_list' => [
            'title' => 'labels.seller_tours.seller_product_list.title',
            'description' => 'labels.seller_tours.seller_product_list.description',
            'group' => 'labels.seller_tours.groups.catalog',
            'permissions' => [
            'product.view',
        ],
            'icon' => 'ti ti-compass',
            'stages' => [
            [
            'route' => 'seller.products.index',
            'steps' => [
            [
            'selector' => '#insight-total-products',
            'title' => 'labels.seller_tours.seller_product_list.stage_0_step_0.title',
            'description' => 'labels.seller_tours.seller_product_list.stage_0_step_0.description',
        ],
            [
            'selector' => '#productStatusFilter',
            'title' => 'labels.seller_tours.seller_product_list.stage_0_step_1.title',
            'description' => 'labels.seller_tours.seller_product_list.stage_0_step_1.description',
        ],
            [
            'selector' => '#productCategoryFilter',
            'title' => 'labels.seller_tours.seller_product_list.stage_0_step_2.title',
            'description' => 'labels.seller_tours.seller_product_list.stage_0_step_2.description',
        ],
            [
            'selector' => '#productStoreFilter',
            'title' => 'labels.seller_tours.seller_product_list.stage_0_step_3.title',
            'description' => 'labels.seller_tours.seller_product_list.stage_0_step_3.description',
        ],
            [
            'selector' => '#products-table',
            'title' => 'labels.seller_tours.seller_product_list.stage_0_step_4.title',
            'description' => 'labels.seller_tours.seller_product_list.stage_0_step_4.description',
        ],
            [
            'selector' => '#add-product-btn',
            'title' => 'labels.seller_tours.seller_product_list.stage_0_step_5.title',
            'description' => 'labels.seller_tours.seller_product_list.stage_0_step_5.description',
            'permission' => 'product.create',
            'optional' => true,
            'offer' => 'seller_product_create',
        ],
        ],
        ],
        ],
        ],
            'seller_product_create' => [
            'title' => 'labels.seller_tours.seller_product_create.title',
            'description' => 'labels.seller_tours.seller_product_create.description',
            'group' => 'labels.seller_tours.groups.catalog',
            'permissions' => [
            'product.create',
        ],
            'icon' => 'ti ti-compass',
            'stages' => [
            [
            'route' => 'seller.products.create',
            'steps' => [
            [
            'selector' => '#product-form-submit .wizard-step[data-step="1"]',
            'title' => 'labels.seller_tours.seller_product_create.stage_0_step_0.title',
            'description' => 'labels.seller_tours.seller_product_create.stage_0_step_0.description',
            'reveal' => [
            'type' => 'wizard',
            'selector' => '#product-category',
            'step' => '1',
        ],
        ],
            [
            'selector' => '#product-form-submit .wizard-step[data-step="2"]',
            'title' => 'labels.seller_tours.seller_product_create.stage_0_step_1.title',
            'description' => 'labels.seller_tours.seller_product_create.stage_0_step_1.description',
            'reveal' => [
            'type' => 'wizard',
            'selector' => '#product-info',
            'step' => '2',
        ],
        ],
            [
            'selector' => '#product-form-submit .wizard-step[data-step="3"]',
            'title' => 'labels.seller_tours.seller_product_create.stage_0_step_2.title',
            'description' => 'labels.seller_tours.seller_product_create.stage_0_step_2.description',
            'reveal' => [
            'type' => 'wizard',
            'selector' => '#product-additional-info',
            'step' => '3',
        ],
        ],
            [
            'selector' => '#product-form-submit .wizard-step[data-step="4"]',
            'title' => 'labels.seller_tours.seller_product_create.stage_0_step_3.title',
            'description' => 'labels.seller_tours.seller_product_create.stage_0_step_3.description',
            'reveal' => [
            'type' => 'wizard',
            'selector' => '#product-variations',
            'step' => '4',
        ],
        ],
            [
            'selector' => '#product-form-submit .wizard-step[data-step="5"]',
            'title' => 'labels.seller_tours.seller_product_create.stage_0_step_4.title',
            'description' => 'labels.seller_tours.seller_product_create.stage_0_step_4.description',
            'reveal' => [
            'type' => 'wizard',
            'selector' => '#product-images',
            'step' => '5',
        ],
        ],
            [
            'selector' => '#product-form-submit .wizard-step[data-step="6"]',
            'title' => 'labels.seller_tours.seller_product_create.stage_0_step_5.title',
            'description' => 'labels.seller_tours.seller_product_create.stage_0_step_5.description',
            'reveal' => [
            'type' => 'wizard',
            'selector' => '#product-policy-features',
            'step' => '6',
        ],
        ],
            [
            'selector' => '#product-form-submit .wizard-step[data-step="7"]',
            'title' => 'labels.seller_tours.seller_product_create.stage_0_step_6.title',
            'description' => 'labels.seller_tours.seller_product_create.stage_0_step_6.description',
            'reveal' => [
            'type' => 'wizard',
            'selector' => '#product-pricing-taxes',
            'step' => '7',
        ],
        ],
            [
            'selector' => '#product-form-submit .card-footer',
            'title' => 'labels.seller_tours.seller_product_create.stage_0_step_7.title',
            'description' => 'labels.seller_tours.seller_product_create.stage_0_step_7.description',
        ],
        ],
        ],
        ],
        ],
            'seller_product_edit' => [
            'title' => 'labels.seller_tours.seller_product_edit.title',
            'description' => 'labels.seller_tours.seller_product_edit.description',
            'group' => 'labels.seller_tours.groups.catalog',
            'permissions' => [
            'product.view',
            'product.edit',
        ],
            'icon' => 'ti ti-compass',
            'stages' => [
            [
            'route' => 'seller.products.edit',
            'selection' => 'select_edit',
            'steps' => [
            [
            'selector' => '#product-form-submit .wizard-step[data-step="1"]',
            'title' => 'labels.seller_tours.seller_product_edit.stage_0_step_0.title',
            'description' => 'labels.seller_tours.seller_product_edit.stage_0_step_0.description',
            'reveal' => [
            'type' => 'wizard',
            'selector' => '#product-category',
            'step' => '1',
        ],
        ],
            [
            'selector' => '#product-form-submit .wizard-step[data-step="2"]',
            'title' => 'labels.seller_tours.seller_product_edit.stage_0_step_1.title',
            'description' => 'labels.seller_tours.seller_product_edit.stage_0_step_1.description',
            'reveal' => [
            'type' => 'wizard',
            'selector' => '#product-info',
            'step' => '2',
        ],
        ],
            [
            'selector' => '#product-form-submit .wizard-step[data-step="3"]',
            'title' => 'labels.seller_tours.seller_product_edit.stage_0_step_2.title',
            'description' => 'labels.seller_tours.seller_product_edit.stage_0_step_2.description',
            'reveal' => [
            'type' => 'wizard',
            'selector' => '#product-additional-info',
            'step' => '3',
        ],
        ],
            [
            'selector' => '#product-form-submit .wizard-step[data-step="4"]',
            'title' => 'labels.seller_tours.seller_product_edit.stage_0_step_3.title',
            'description' => 'labels.seller_tours.seller_product_edit.stage_0_step_3.description',
            'reveal' => [
            'type' => 'wizard',
            'selector' => '#product-variations',
            'step' => '4',
        ],
        ],
            [
            'selector' => '#product-form-submit .wizard-step[data-step="5"]',
            'title' => 'labels.seller_tours.seller_product_edit.stage_0_step_4.title',
            'description' => 'labels.seller_tours.seller_product_edit.stage_0_step_4.description',
            'reveal' => [
            'type' => 'wizard',
            'selector' => '#product-images',
            'step' => '5',
        ],
        ],
            [
            'selector' => '#product-form-submit .wizard-step[data-step="6"]',
            'title' => 'labels.seller_tours.seller_product_edit.stage_0_step_5.title',
            'description' => 'labels.seller_tours.seller_product_edit.stage_0_step_5.description',
            'reveal' => [
            'type' => 'wizard',
            'selector' => '#product-policy-features',
            'step' => '6',
        ],
        ],
            [
            'selector' => '#product-form-submit .wizard-step[data-step="7"]',
            'title' => 'labels.seller_tours.seller_product_edit.stage_0_step_6.title',
            'description' => 'labels.seller_tours.seller_product_edit.stage_0_step_6.description',
            'reveal' => [
            'type' => 'wizard',
            'selector' => '#product-pricing-taxes',
            'step' => '7',
        ],
        ],
            [
            'selector' => '#product-form-submit .card-footer',
            'title' => 'labels.seller_tours.seller_product_edit.stage_0_step_7.title',
            'description' => 'labels.seller_tours.seller_product_edit.stage_0_step_7.description',
        ],
        ],
            'record' => true,
            'listing' => 'seller.products.index',
        ],
        ],
        ],
            'seller_bulk_upload' => [
            'title' => 'labels.seller_tours.seller_bulk_upload.title',
            'description' => 'labels.seller_tours.seller_bulk_upload.description',
            'group' => 'labels.seller_tours.groups.catalog',
            'permissions' => [
            'product.create',
        ],
            'icon' => 'ti ti-compass',
            'stages' => [
            [
            'route' => 'seller.products.bulk-upload.page',
            'steps' => [
            [
            'selector' => '[data-seller-tour=\'bulk-upload\']',
            'title' => 'labels.seller_tours.seller_bulk_upload.stage_0_step_0.title',
            'description' => 'labels.seller_tours.seller_bulk_upload.stage_0_step_0.description',
        ],
            [
            'selector' => '#csv-file',
            'title' => 'labels.seller_tours.seller_bulk_upload.stage_0_step_1.title',
            'description' => 'labels.seller_tours.seller_bulk_upload.stage_0_step_1.description',
        ],
            [
            'selector' => '#bulk-upload-preview-submit',
            'title' => 'labels.seller_tours.seller_bulk_upload.stage_0_step_2.title',
            'description' => 'labels.seller_tours.seller_bulk_upload.stage_0_step_2.description',
        ],
            [
            'selector' => '#bulk-upload-preview-table',
            'title' => 'labels.seller_tours.seller_bulk_upload.stage_0_step_3.title',
            'description' => 'labels.seller_tours.seller_bulk_upload.stage_0_step_3.description',
            'optional' => true,
        ],
            [
            'selector' => '#bulk-operation-select',
            'title' => 'labels.seller_tours.seller_bulk_upload.stage_0_step_4.title',
            'description' => 'labels.seller_tours.seller_bulk_upload.stage_0_step_4.description',
            'optional' => true,
        ],
            [
            'selector' => '#bulk-upload-confirm-submit',
            'title' => 'labels.seller_tours.seller_bulk_upload.stage_0_step_5.title',
            'description' => 'labels.seller_tours.seller_bulk_upload.stage_0_step_5.description',
            'optional' => true,
        ],
        ],
        ],
        ],
        ],
            'seller_attributes' => [
            'title' => 'labels.seller_tours.seller_attributes.title',
            'description' => 'labels.seller_tours.seller_attributes.description',
            'group' => 'labels.seller_tours.groups.catalog',
            'permissions' => [
            'attribute.view',
        ],
            'icon' => 'ti ti-compass',
            'stages' => [
            [
            'route' => 'seller.attributes.index',
            'steps' => [
            [
            'selector' => '#attributes-table',
            'title' => 'labels.seller_tours.seller_attributes.stage_0_step_0.title',
            'description' => 'labels.seller_tours.seller_attributes.stage_0_step_0.description',
        ],
            [
            'selector' => '#attribute-values-table',
            'title' => 'labels.seller_tours.seller_attributes.stage_0_step_1.title',
            'description' => 'labels.seller_tours.seller_attributes.stage_0_step_1.description',
            'reveal' => [
            'type' => 'tab',
            'selector' => 'a[href=\'#tabs-profile-ex2\']',
        ],
            'optional' => true,
        ],
            [
            'selector' => '#attribute-create-update-modal #label',
            'title' => 'labels.seller_tours.seller_attributes.stage_0_step_2.title',
            'description' => 'labels.seller_tours.seller_attributes.stage_0_step_2.description',
            'permission' => 'attribute.create',
            'optional' => true,
            'reveal' => [
            'type' => 'modal',
            'selector' => '#attribute-create-update-modal',
        ],
        ],
            [
            'selector' => '#attribute-create-update-modal #swatche_type',
            'title' => 'labels.seller_tours.seller_attributes.stage_0_step_3.title',
            'description' => 'labels.seller_tours.seller_attributes.stage_0_step_3.description',
            'permission' => 'attribute.create',
            'optional' => true,
        ],
            [
            'selector' => '#attribute-value-create-update-modal #attribute_id',
            'title' => 'labels.seller_tours.seller_attributes.stage_0_step_4.title',
            'description' => 'labels.seller_tours.seller_attributes.stage_0_step_4.description',
            'permission' => 'attribute.create',
            'optional' => true,
            'reveal' => [
            'type' => 'modal',
            'selector' => '#attribute-value-create-update-modal',
        ],
        ],
            [
            'selector' => '#dynamic-fields-container',
            'title' => 'labels.seller_tours.seller_attributes.stage_0_step_5.title',
            'description' => 'labels.seller_tours.seller_attributes.stage_0_step_5.description',
            'permission' => 'attribute.create',
            'optional' => true,
        ],
        ],
        ],
        ],
        ],
            'seller_faqs' => [
            'title' => 'labels.seller_tours.seller_faqs.title',
            'description' => 'labels.seller_tours.seller_faqs.description',
            'group' => 'labels.seller_tours.groups.catalog',
            'permissions' => [
            'product_faq.view',
        ],
            'icon' => 'ti ti-compass',
            'stages' => [
            [
            'route' => 'seller.product_faqs.index',
            'steps' => [
            [
            'selector' => '#product-faqs-table',
            'title' => 'labels.seller_tours.seller_faqs.stage_0_step_0.title',
            'description' => 'labels.seller_tours.seller_faqs.stage_0_step_0.description',
        ],
            [
            'selector' => '#product-faqs-table_wrapper .dt-search, #product-faqs-table_wrapper .dataTables_filter',
            'title' => 'labels.seller_tours.seller_faqs.stage_0_step_1.title',
            'description' => 'labels.seller_tours.seller_faqs.stage_0_step_1.description',
            'optional' => true,
        ],
            [
            'selector' => '#product-faqs-table_wrapper .dt-length, #product-faqs-table_wrapper .dataTables_length',
            'title' => 'labels.seller_tours.seller_faqs.stage_0_step_2.title',
            'description' => 'labels.seller_tours.seller_faqs.stage_0_step_2.description',
            'optional' => true,
        ],
            [
            'selector' => '#product-faqs-table_wrapper .dt-paging, #product-faqs-table_wrapper .dataTables_paginate',
            'title' => 'labels.seller_tours.seller_faqs.stage_0_step_3.title',
            'description' => 'labels.seller_tours.seller_faqs.stage_0_step_3.description',
            'optional' => true,
        ],
            [
            'selector' => '#product-faq-form #select-product',
            'title' => 'labels.seller_tours.seller_faqs.stage_0_step_4.title',
            'description' => 'labels.seller_tours.seller_faqs.stage_0_step_4.description',
            'permission' => 'product_faq.create',
            'optional' => true,
            'reveal' => [
            'type' => 'modal',
            'selector' => '#product-faq-modal',
        ],
        ],
            [
            'selector' => '#question',
            'title' => 'labels.seller_tours.seller_faqs.stage_0_step_5.title',
            'description' => 'labels.seller_tours.seller_faqs.stage_0_step_5.description',
            'permission' => 'product_faq.create',
            'optional' => true,
        ],
            [
            'selector' => '#answer',
            'title' => 'labels.seller_tours.seller_faqs.stage_0_step_6.title',
            'description' => 'labels.seller_tours.seller_faqs.stage_0_step_6.description',
            'permission' => 'product_faq.create',
            'optional' => true,
        ],
        ],
        ],
        ],
        ],
            'seller_addon_groups' => [
            'title' => 'labels.seller_tours.seller_addon_groups.title',
            'description' => 'labels.seller_tours.seller_addon_groups.description',
            'group' => 'labels.seller_tours.groups.catalog',
            'permissions' => [
            'addon_group.view',
        ],
            'icon' => 'ti ti-compass',
            'stages' => [
            [
            'route' => 'seller.addon-groups.index',
            'steps' => [
            [
            'selector' => '#addon-groups-table',
            'title' => 'labels.seller_tours.seller_addon_groups.stage_0_step_0.title',
            'description' => 'labels.seller_tours.seller_addon_groups.stage_0_step_0.description',
        ],
            [
            'selector' => '#addon-groups-table_wrapper .dt-search, #addon-groups-table_wrapper .dataTables_filter',
            'title' => 'labels.seller_tours.seller_addon_groups.stage_0_step_1.title',
            'description' => 'labels.seller_tours.seller_addon_groups.stage_0_step_1.description',
            'optional' => true,
        ],
            [
            'selector' => '#addon-groups-table_wrapper .dt-length, #addon-groups-table_wrapper .dataTables_length',
            'title' => 'labels.seller_tours.seller_addon_groups.stage_0_step_2.title',
            'description' => 'labels.seller_tours.seller_addon_groups.stage_0_step_2.description',
            'optional' => true,
        ],
            [
            'selector' => '#addon-groups-table_wrapper .dt-paging, #addon-groups-table_wrapper .dataTables_paginate',
            'title' => 'labels.seller_tours.seller_addon_groups.stage_0_step_3.title',
            'description' => 'labels.seller_tours.seller_addon_groups.stage_0_step_3.description',
            'optional' => true,
        ],
        ],
        ],
            [
            'route' => 'seller.addon-groups.create',
            'steps' => [
            [
            'selector' => '#addon-title',
            'title' => 'labels.seller_tours.seller_addon_groups.stage_1_step_0.title',
            'description' => 'labels.seller_tours.seller_addon_groups.stage_1_step_0.description',
        ],
            [
            'selector' => '[name=\'selection_type\']',
            'title' => 'labels.seller_tours.seller_addon_groups.stage_1_step_1.title',
            'description' => 'labels.seller_tours.seller_addon_groups.stage_1_step_1.description',
        ],
            [
            'selector' => '[name=\'is_required\']',
            'title' => 'labels.seller_tours.seller_addon_groups.stage_1_step_2.title',
            'description' => 'labels.seller_tours.seller_addon_groups.stage_1_step_2.description',
        ],
            [
            'selector' => '#addon-items-wrapper',
            'title' => 'labels.seller_tours.seller_addon_groups.stage_1_step_3.title',
            'description' => 'labels.seller_tours.seller_addon_groups.stage_1_step_3.description',
        ],
            [
            'selector' => '#addon-save-btn',
            'title' => 'labels.seller_tours.seller_addon_groups.stage_1_step_4.title',
            'description' => 'labels.seller_tours.seller_addon_groups.stage_1_step_4.description',
        ],
        ],
            'permission' => 'addon_group.create',
        ],
        ],
        ],
            'seller_product_addons' => [
            'title' => 'labels.seller_tours.seller_product_addons.title',
            'description' => 'labels.seller_tours.seller_product_addons.description',
            'group' => 'labels.seller_tours.groups.catalog',
            'permissions' => [
            'product_addon.view',
        ],
            'icon' => 'ti ti-compass',
            'stages' => [
            [
            'route' => 'seller.product-addons.index',
            'steps' => [
            [
            'selector' => '#product-addons-table',
            'title' => 'labels.seller_tours.seller_product_addons.stage_0_step_0.title',
            'description' => 'labels.seller_tours.seller_product_addons.stage_0_step_0.description',
        ],
            [
            'selector' => '#product-addons-table_wrapper .dt-search, #product-addons-table_wrapper .dataTables_filter',
            'title' => 'labels.seller_tours.seller_product_addons.stage_0_step_1.title',
            'description' => 'labels.seller_tours.seller_product_addons.stage_0_step_1.description',
            'optional' => true,
        ],
            [
            'selector' => '#product-addons-table_wrapper .dt-length, #product-addons-table_wrapper .dataTables_length',
            'title' => 'labels.seller_tours.seller_product_addons.stage_0_step_2.title',
            'description' => 'labels.seller_tours.seller_product_addons.stage_0_step_2.description',
            'optional' => true,
        ],
            [
            'selector' => '#product-addons-table_wrapper .dt-paging, #product-addons-table_wrapper .dataTables_paginate',
            'title' => 'labels.seller_tours.seller_product_addons.stage_0_step_3.title',
            'description' => 'labels.seller_tours.seller_product_addons.stage_0_step_3.description',
            'optional' => true,
        ],
        ],
        ],
            [
            'route' => 'seller.product-addons.create',
            'steps' => [
            [
            'selector' => '#pa-products-multi',
            'title' => 'labels.seller_tours.seller_product_addons.stage_1_step_0.title',
            'description' => 'labels.seller_tours.seller_product_addons.stage_1_step_0.description',
        ],
            [
            'selector' => '#pa-variants-multi',
            'title' => 'labels.seller_tours.seller_product_addons.stage_1_step_1.title',
            'description' => 'labels.seller_tours.seller_product_addons.stage_1_step_1.description',
        ],
            [
            'selector' => '#pa-groups-multi',
            'title' => 'labels.seller_tours.seller_product_addons.stage_1_step_2.title',
            'description' => 'labels.seller_tours.seller_product_addons.stage_1_step_2.description',
        ],
            [
            'selector' => '#pa-attachments-root',
            'title' => 'labels.seller_tours.seller_product_addons.stage_1_step_3.title',
            'description' => 'labels.seller_tours.seller_product_addons.stage_1_step_3.description',
        ],
            [
            'selector' => '#pa-save-btn',
            'title' => 'labels.seller_tours.seller_product_addons.stage_1_step_4.title',
            'description' => 'labels.seller_tours.seller_product_addons.stage_1_step_4.description',
        ],
        ],
            'permission' => 'product_addon.create',
        ],
        ],
        ],
            'seller_addon_inventory' => [
            'title' => 'labels.seller_tours.seller_addon_inventory.title',
            'description' => 'labels.seller_tours.seller_addon_inventory.description',
            'group' => 'labels.seller_tours.groups.catalog',
            'permissions' => [
            'store_addon_item.view',
        ],
            'icon' => 'ti ti-compass',
            'stages' => [
            [
            'route' => 'seller.store-addon-items.index',
            'steps' => [
            [
            'selector' => '#filter-addon-group',
            'title' => 'labels.seller_tours.seller_addon_inventory.stage_0_step_0.title',
            'description' => 'labels.seller_tours.seller_addon_inventory.stage_0_step_0.description',
        ],
            [
            'selector' => '#filter-store',
            'title' => 'labels.seller_tours.seller_addon_inventory.stage_0_step_1.title',
            'description' => 'labels.seller_tours.seller_addon_inventory.stage_0_step_1.description',
        ],
            [
            'selector' => '#store-addon-items-table',
            'title' => 'labels.seller_tours.seller_addon_inventory.stage_0_step_2.title',
            'description' => 'labels.seller_tours.seller_addon_inventory.stage_0_step_2.description',
        ],
            [
            'selector' => '#store-addon-items-table_wrapper .dt-search, #store-addon-items-table_wrapper .dataTables_filter',
            'title' => 'labels.seller_tours.seller_addon_inventory.stage_0_step_3.title',
            'description' => 'labels.seller_tours.seller_addon_inventory.stage_0_step_3.description',
            'optional' => true,
        ],
            [
            'selector' => '#store-addon-items-table_wrapper .dt-length, #store-addon-items-table_wrapper .dataTables_length',
            'title' => 'labels.seller_tours.seller_addon_inventory.stage_0_step_4.title',
            'description' => 'labels.seller_tours.seller_addon_inventory.stage_0_step_4.description',
            'optional' => true,
        ],
            [
            'selector' => '#store-addon-items-table_wrapper .dt-paging, #store-addon-items-table_wrapper .dataTables_paginate',
            'title' => 'labels.seller_tours.seller_addon_inventory.stage_0_step_5.title',
            'description' => 'labels.seller_tours.seller_addon_inventory.stage_0_step_5.description',
            'optional' => true,
        ],
        ],
        ],
            [
            'route' => 'seller.store-addon-items.create',
            'steps' => [
            [
            'selector' => '#sai-bulk-form',
            'title' => 'labels.seller_tours.seller_addon_inventory.stage_1_step_0.title',
            'description' => 'labels.seller_tours.seller_addon_inventory.stage_1_step_0.description',
        ],
            [
            'selector' => '#sai-store-count',
            'title' => 'labels.seller_tours.seller_addon_inventory.stage_1_step_1.title',
            'description' => 'labels.seller_tours.seller_addon_inventory.stage_1_step_1.description',
        ],
            [
            'selector' => '#sai-rows-table',
            'title' => 'labels.seller_tours.seller_addon_inventory.stage_1_step_2.title',
            'description' => 'labels.seller_tours.seller_addon_inventory.stage_1_step_2.description',
        ],
            [
            'selector' => '#sai-impact-hint',
            'title' => 'labels.seller_tours.seller_addon_inventory.stage_1_step_3.title',
            'description' => 'labels.seller_tours.seller_addon_inventory.stage_1_step_3.description',
        ],
            [
            'selector' => '#sai-save-btn',
            'title' => 'labels.seller_tours.seller_addon_inventory.stage_1_step_4.title',
            'description' => 'labels.seller_tours.seller_addon_inventory.stage_1_step_4.description',
        ],
        ],
            'permission' => 'store_addon_item.create',
        ],
        ],
        ],
            'seller_categories' => [
            'title' => 'labels.seller_tours.seller_categories.title',
            'description' => 'labels.seller_tours.seller_categories.description',
            'group' => 'labels.seller_tours.groups.catalog',
            'permissions' => [
            'category.view',
        ],
            'icon' => 'ti ti-compass',
            'stages' => [
            [
            'route' => 'seller.categories.index',
            'steps' => [
            [
            'selector' => '#categories-table',
            'title' => 'labels.seller_tours.seller_categories.stage_0_step_0.title',
            'description' => 'labels.seller_tours.seller_categories.stage_0_step_0.description',
        ],
            [
            'selector' => '#categories-table_wrapper .dt-search, #categories-table_wrapper .dataTables_filter',
            'title' => 'labels.seller_tours.seller_categories.stage_0_step_1.title',
            'description' => 'labels.seller_tours.seller_categories.stage_0_step_1.description',
            'optional' => true,
        ],
            [
            'selector' => '#categories-table_wrapper .dt-length, #categories-table_wrapper .dataTables_length',
            'title' => 'labels.seller_tours.seller_categories.stage_0_step_2.title',
            'description' => 'labels.seller_tours.seller_categories.stage_0_step_2.description',
            'optional' => true,
        ],
            [
            'selector' => '#categories-table_wrapper .dt-paging, #categories-table_wrapper .dataTables_paginate',
            'title' => 'labels.seller_tours.seller_categories.stage_0_step_3.title',
            'description' => 'labels.seller_tours.seller_categories.stage_0_step_3.description',
            'optional' => true,
        ],
        ],
        ],
        ],
        ],
            'seller_brands' => [
            'title' => 'labels.seller_tours.seller_brands.title',
            'description' => 'labels.seller_tours.seller_brands.description',
            'group' => 'labels.seller_tours.groups.catalog',
            'permissions' => [
            'brand.view',
        ],
            'icon' => 'ti ti-compass',
            'stages' => [
            [
            'route' => 'seller.brands.index',
            'steps' => [
            [
            'selector' => '#brands-table',
            'title' => 'labels.seller_tours.seller_brands.stage_0_step_0.title',
            'description' => 'labels.seller_tours.seller_brands.stage_0_step_0.description',
        ],
            [
            'selector' => '#brands-table_wrapper .dt-search, #brands-table_wrapper .dataTables_filter',
            'title' => 'labels.seller_tours.seller_brands.stage_0_step_1.title',
            'description' => 'labels.seller_tours.seller_brands.stage_0_step_1.description',
            'optional' => true,
        ],
            [
            'selector' => '#brands-table_wrapper .dt-length, #brands-table_wrapper .dataTables_length',
            'title' => 'labels.seller_tours.seller_brands.stage_0_step_2.title',
            'description' => 'labels.seller_tours.seller_brands.stage_0_step_2.description',
            'optional' => true,
        ],
            [
            'selector' => '#brands-table_wrapper .dt-paging, #brands-table_wrapper .dataTables_paginate',
            'title' => 'labels.seller_tours.seller_brands.stage_0_step_3.title',
            'description' => 'labels.seller_tours.seller_brands.stage_0_step_3.description',
            'optional' => true,
        ],
        ],
        ],
        ],
        ],
            'seller_tax_rates' => [
            'title' => 'labels.seller_tours.seller_tax_rates.title',
            'description' => 'labels.seller_tours.seller_tax_rates.description',
            'group' => 'labels.seller_tours.groups.finance',
            'permissions' => [
            'tax_rate.view',
        ],
            'icon' => 'ti ti-compass',
            'stages' => [
            [
            'route' => 'seller.tax-rates.index',
            'steps' => [
            [
            'selector' => '#tax-group-table',
            'title' => 'labels.seller_tours.seller_tax_rates.stage_0_step_0.title',
            'description' => 'labels.seller_tours.seller_tax_rates.stage_0_step_0.description',
        ],
            [
            'selector' => '#tax-group-table_wrapper .dt-search, #tax-group-table_wrapper .dataTables_filter',
            'title' => 'labels.seller_tours.seller_tax_rates.stage_0_step_1.title',
            'description' => 'labels.seller_tours.seller_tax_rates.stage_0_step_1.description',
            'optional' => true,
        ],
            [
            'selector' => '#tax-group-table_wrapper .dt-length, #tax-group-table_wrapper .dataTables_length',
            'title' => 'labels.seller_tours.seller_tax_rates.stage_0_step_2.title',
            'description' => 'labels.seller_tours.seller_tax_rates.stage_0_step_2.description',
            'optional' => true,
        ],
            [
            'selector' => '#tax-group-table_wrapper .dt-paging, #tax-group-table_wrapper .dataTables_paginate',
            'title' => 'labels.seller_tours.seller_tax_rates.stage_0_step_3.title',
            'description' => 'labels.seller_tours.seller_tax_rates.stage_0_step_3.description',
            'optional' => true,
        ],
        ],
        ],
        ],
        ],
            'seller_earnings' => [
            'title' => 'labels.seller_tours.seller_earnings.title',
            'description' => 'labels.seller_tours.seller_earnings.description',
            'group' => 'labels.seller_tours.groups.finance',
            'permissions' => [
            'earning.view',
        ],
            'icon' => 'ti ti-compass',
            'stages' => [
            [
            'route' => 'seller.commissions.index',
            'steps' => [
            [
            'selector' => '#storeFilter',
            'title' => 'labels.seller_tours.seller_earnings.stage_0_step_0.title',
            'description' => 'labels.seller_tours.seller_earnings.stage_0_step_0.description',
        ],
            [
            'selector' => '#commissions-table',
            'title' => 'labels.seller_tours.seller_earnings.stage_0_step_1.title',
            'description' => 'labels.seller_tours.seller_earnings.stage_0_step_1.description',
        ],
            [
            'selector' => '#debits-table',
            'title' => 'labels.seller_tours.seller_earnings.stage_0_step_2.title',
            'description' => 'labels.seller_tours.seller_earnings.stage_0_step_2.description',
            'reveal' => [
            'type' => 'tab',
            'selector' => 'a[href=\'#tab-debits\']',
        ],
            'optional' => true,
        ],
            [
            'selector' => 'a[data-seller-tour=\'settlement-history\']',
            'title' => 'labels.seller_tours.seller_earnings.stage_0_step_3.title',
            'description' => 'labels.seller_tours.seller_earnings.stage_0_step_3.description',
        ],
        ],
        ],
            [
            'route' => 'seller.commissions.history',
            'steps' => [
            [
            'selector' => '#commissions-history-table',
            'title' => 'labels.seller_tours.seller_earnings.stage_1_step_0.title',
            'description' => 'labels.seller_tours.seller_earnings.stage_1_step_0.description',
        ],
            [
            'selector' => '#commissions-history-table_wrapper .dt-search, #commissions-history-table_wrapper .dataTables_filter',
            'title' => 'labels.seller_tours.seller_earnings.stage_1_step_1.title',
            'description' => 'labels.seller_tours.seller_earnings.stage_1_step_1.description',
            'optional' => true,
        ],
            [
            'selector' => '#commissions-history-table_wrapper .dt-length, #commissions-history-table_wrapper .dataTables_length',
            'title' => 'labels.seller_tours.seller_earnings.stage_1_step_2.title',
            'description' => 'labels.seller_tours.seller_earnings.stage_1_step_2.description',
            'optional' => true,
        ],
            [
            'selector' => '#commissions-history-table_wrapper .dt-paging, #commissions-history-table_wrapper .dataTables_paginate',
            'title' => 'labels.seller_tours.seller_earnings.stage_1_step_3.title',
            'description' => 'labels.seller_tours.seller_earnings.stage_1_step_3.description',
            'optional' => true,
        ],
        ],
        ],
        ],
        ],
            'seller_wallet' => [
            'title' => 'labels.seller_tours.seller_wallet.title',
            'description' => 'labels.seller_tours.seller_wallet.description',
            'group' => 'labels.seller_tours.groups.finance',
            'permissions' => [
            'wallet.view',
        ],
            'icon' => 'ti ti-compass',
            'stages' => [
            [
            'route' => 'seller.wallet.index',
            'steps' => [
            [
            'selector' => '#wallet-balance-summary',
            'title' => 'labels.seller_tours.seller_wallet.stage_0_step_0.title',
            'description' => 'labels.seller_tours.seller_wallet.stage_0_step_0.description',
        ],
            [
            'selector' => '#wallet-transactions-btn',
            'title' => 'labels.seller_tours.seller_wallet.stage_0_step_1.title',
            'description' => 'labels.seller_tours.seller_wallet.stage_0_step_1.description',
        ],
            [
            'selector' => '#wallet-balance-summary',
            'title' => 'labels.seller_tours.seller_wallet.stage_0_step_2.title',
            'description' => 'labels.seller_tours.seller_wallet.stage_0_step_2.description',
        ],
            [
            'selector' => '#wallet-balance-summary',
            'title' => 'labels.seller_tours.seller_wallet.stage_0_step_3.title',
            'description' => 'labels.seller_tours.seller_wallet.stage_0_step_3.description',
        ],
        ],
        ],
            [
            'route' => 'seller.wallet.transactions',
            'steps' => [
            [
            'selector' => '#typeFilter',
            'title' => 'labels.seller_tours.seller_wallet.stage_1_step_0.title',
            'description' => 'labels.seller_tours.seller_wallet.stage_1_step_0.description',
        ],
            [
            'selector' => '#wallet-transactions-table, #transactions-table',
            'title' => 'labels.seller_tours.seller_wallet.stage_1_step_1.title',
            'description' => 'labels.seller_tours.seller_wallet.stage_1_step_1.description',
        ],
            [
            'selector' => '#wallet-transactions-table_wrapper, #transactions-table_wrapper .dt-search, #wallet-transactions-table, #transactions-table_wrapper .dataTables_filter',
            'title' => 'labels.seller_tours.seller_wallet.stage_1_step_2.title',
            'description' => 'labels.seller_tours.seller_wallet.stage_1_step_2.description',
            'optional' => true,
        ],
            [
            'selector' => '#wallet-transactions-table_wrapper, #transactions-table_wrapper .dt-length, #wallet-transactions-table, #transactions-table_wrapper .dataTables_length',
            'title' => 'labels.seller_tours.seller_wallet.stage_1_step_3.title',
            'description' => 'labels.seller_tours.seller_wallet.stage_1_step_3.description',
            'optional' => true,
        ],
            [
            'selector' => '#wallet-transactions-table_wrapper, #transactions-table_wrapper .dt-paging, #wallet-transactions-table, #transactions-table_wrapper .dataTables_paginate',
            'title' => 'labels.seller_tours.seller_wallet.stage_1_step_4.title',
            'description' => 'labels.seller_tours.seller_wallet.stage_1_step_4.description',
            'optional' => true,
        ],
        ],
        ],
        ],
        ],
            'seller_withdrawals' => [
            'title' => 'labels.seller_tours.seller_withdrawals.title',
            'description' => 'labels.seller_tours.seller_withdrawals.description',
            'group' => 'labels.seller_tours.groups.finance',
            'permissions' => [
            'wallet.view',
        ],
            'icon' => 'ti ti-compass',
            'stages' => [
            [
            'route' => 'seller.withdrawals.index',
            'steps' => [
            [
            'selector' => '#withdrawal-page-1',
            'title' => 'labels.seller_tours.seller_withdrawals.stage_0_step_0.title',
            'description' => 'labels.seller_tours.seller_withdrawals.stage_0_step_0.description',
        ],
            [
            'selector' => '#amount',
            'title' => 'labels.seller_tours.seller_withdrawals.stage_0_step_1.title',
            'description' => 'labels.seller_tours.seller_withdrawals.stage_0_step_1.description',
            'permission' => 'withdrawal.request',
            'optional' => true,
        ],
            [
            'selector' => '#note',
            'title' => 'labels.seller_tours.seller_withdrawals.stage_0_step_2.title',
            'description' => 'labels.seller_tours.seller_withdrawals.stage_0_step_2.description',
            'permission' => 'withdrawal.request',
            'optional' => true,
        ],
            [
            'selector' => '#submit-btn',
            'title' => 'labels.seller_tours.seller_withdrawals.stage_0_step_3.title',
            'description' => 'labels.seller_tours.seller_withdrawals.stage_0_step_3.description',
            'permission' => 'withdrawal.request',
            'optional' => true,
        ],
            [
            'selector' => '#withdrawal-requests-table',
            'title' => 'labels.seller_tours.seller_withdrawals.stage_0_step_4.title',
            'description' => 'labels.seller_tours.seller_withdrawals.stage_0_step_4.description',
        ],
            [
            'selector' => '#withdrawal-history-btn',
            'title' => 'labels.seller_tours.seller_withdrawals.stage_0_step_5.title',
            'description' => 'labels.seller_tours.seller_withdrawals.stage_0_step_5.description',
            'permission' => 'withdrawal.view',
            'optional' => true,
        ],
        ],
        ],
            [
            'route' => 'seller.withdrawals.history',
            'steps' => [
            [
            'selector' => '#withdrawal-history-table',
            'title' => 'labels.seller_tours.seller_withdrawals.stage_1_step_0.title',
            'description' => 'labels.seller_tours.seller_withdrawals.stage_1_step_0.description',
        ],
            [
            'selector' => '#withdrawal-history-table_wrapper .dt-search, #withdrawal-history-table_wrapper .dataTables_filter',
            'title' => 'labels.seller_tours.seller_withdrawals.stage_1_step_1.title',
            'description' => 'labels.seller_tours.seller_withdrawals.stage_1_step_1.description',
            'optional' => true,
        ],
            [
            'selector' => '#withdrawal-history-table_wrapper .dt-length, #withdrawal-history-table_wrapper .dataTables_length',
            'title' => 'labels.seller_tours.seller_withdrawals.stage_1_step_2.title',
            'description' => 'labels.seller_tours.seller_withdrawals.stage_1_step_2.description',
            'optional' => true,
        ],
            [
            'selector' => '#withdrawal-history-table_wrapper .dt-paging, #withdrawal-history-table_wrapper .dataTables_paginate',
            'title' => 'labels.seller_tours.seller_withdrawals.stage_1_step_3.title',
            'description' => 'labels.seller_tours.seller_withdrawals.stage_1_step_3.description',
            'optional' => true,
        ],
        ],
            'permission' => 'withdrawal.view',
        ],
        ],
        ],
            'seller_subscriptions' => [
            'title' => 'labels.seller_tours.seller_subscriptions.title',
            'description' => 'labels.seller_tours.seller_subscriptions.description',
            'group' => 'labels.seller_tours.groups.finance',
            'permissions' => [
            'subscription.view',
        ],
            'icon' => 'ti ti-compass',
            'stages' => [
            [
            'route' => 'seller.subscription-plans.index',
            'steps' => [
            [
            'selector' => '#subscription-index-target',
            'title' => 'labels.seller_tours.seller_subscriptions.stage_0_step_0.title',
            'description' => 'labels.seller_tours.seller_subscriptions.stage_0_step_0.description',
        ],
            [
            'selector' => '#subscription-index-target',
            'title' => 'labels.seller_tours.seller_subscriptions.stage_0_step_1.title',
            'description' => 'labels.seller_tours.seller_subscriptions.stage_0_step_1.description',
        ],
            [
            'selector' => '#subscription-index-target',
            'title' => 'labels.seller_tours.seller_subscriptions.stage_0_step_2.title',
            'description' => 'labels.seller_tours.seller_subscriptions.stage_0_step_2.description',
        ],
            [
            'selector' => '#subscription-index-target',
            'title' => 'labels.seller_tours.seller_subscriptions.stage_0_step_3.title',
            'description' => 'labels.seller_tours.seller_subscriptions.stage_0_step_3.description',
        ],
        ],
        ],
            [
            'route' => 'seller.subscription-plans.current',
            'steps' => [
            [
            'selector' => '#subscription-target-1',
            'title' => 'labels.seller_tours.seller_subscriptions.stage_1_step_0.title',
            'description' => 'labels.seller_tours.seller_subscriptions.stage_1_step_0.description',
        ],
            [
            'selector' => '#subscription-target-1',
            'title' => 'labels.seller_tours.seller_subscriptions.stage_1_step_1.title',
            'description' => 'labels.seller_tours.seller_subscriptions.stage_1_step_1.description',
        ],
            [
            'selector' => '#subscription-target-1',
            'title' => 'labels.seller_tours.seller_subscriptions.stage_1_step_2.title',
            'description' => 'labels.seller_tours.seller_subscriptions.stage_1_step_2.description',
        ],
            [
            'selector' => '#subscription-target-1',
            'title' => 'labels.seller_tours.seller_subscriptions.stage_1_step_3.title',
            'description' => 'labels.seller_tours.seller_subscriptions.stage_1_step_3.description',
        ],
        ],
        ],
            [
            'route' => 'seller.subscription-plans.history',
            'steps' => [
            [
            'selector' => '#subscription-history-table',
            'title' => 'labels.seller_tours.seller_subscriptions.stage_2_step_0.title',
            'description' => 'labels.seller_tours.seller_subscriptions.stage_2_step_0.description',
        ],
            [
            'selector' => '#subscription-history-table_wrapper .dt-search, #subscription-history-table_wrapper .dataTables_filter',
            'title' => 'labels.seller_tours.seller_subscriptions.stage_2_step_1.title',
            'description' => 'labels.seller_tours.seller_subscriptions.stage_2_step_1.description',
            'optional' => true,
        ],
            [
            'selector' => '#subscription-history-table_wrapper .dt-length, #subscription-history-table_wrapper .dataTables_length',
            'title' => 'labels.seller_tours.seller_subscriptions.stage_2_step_2.title',
            'description' => 'labels.seller_tours.seller_subscriptions.stage_2_step_2.description',
            'optional' => true,
        ],
            [
            'selector' => '#subscription-history-table_wrapper .dt-paging, #subscription-history-table_wrapper .dataTables_paginate',
            'title' => 'labels.seller_tours.seller_subscriptions.stage_2_step_3.title',
            'description' => 'labels.seller_tours.seller_subscriptions.stage_2_step_3.description',
            'optional' => true,
        ],
        ],
        ],
        ],
            'feature' => 'subscription',
        ],
            'seller_ad_dashboard' => [
            'title' => 'labels.seller_tours.seller_ad_dashboard.title',
            'description' => 'labels.seller_tours.seller_ad_dashboard.description',
            'group' => 'labels.seller_tours.groups.marketing',
            'permissions' => [
            'ad_campaign.dashboard_view',
        ],
            'icon' => 'ti ti-compass',
            'stages' => [
            [
            'route' => 'seller.ads.campaigns.dashboard',
            'steps' => [
            [
            'selector' => '#periodSelector',
            'title' => 'labels.seller_tours.seller_ad_dashboard.stage_0_step_0.title',
            'description' => 'labels.seller_tours.seller_ad_dashboard.stage_0_step_0.description',
        ],
            [
            'selector' => '#statWallet',
            'title' => 'labels.seller_tours.seller_ad_dashboard.stage_0_step_1.title',
            'description' => 'labels.seller_tours.seller_ad_dashboard.stage_0_step_1.description',
        ],
            [
            'selector' => '#statSpent',
            'title' => 'labels.seller_tours.seller_ad_dashboard.stage_0_step_2.title',
            'description' => 'labels.seller_tours.seller_ad_dashboard.stage_0_step_2.description',
        ],
            [
            'selector' => '#statClicks',
            'title' => 'labels.seller_tours.seller_ad_dashboard.stage_0_step_3.title',
            'description' => 'labels.seller_tours.seller_ad_dashboard.stage_0_step_3.description',
        ],
            [
            'selector' => '#spendTrendChart',
            'title' => 'labels.seller_tours.seller_ad_dashboard.stage_0_step_4.title',
            'description' => 'labels.seller_tours.seller_ad_dashboard.stage_0_step_4.description',
        ],
            [
            'selector' => '#topCampaignsTable',
            'title' => 'labels.seller_tours.seller_ad_dashboard.stage_0_step_5.title',
            'description' => 'labels.seller_tours.seller_ad_dashboard.stage_0_step_5.description',
        ],
        ],
        ],
        ],
            'feature' => 'ads',
        ],
            'seller_ad_campaigns' => [
            'title' => 'labels.seller_tours.seller_ad_campaigns.title',
            'description' => 'labels.seller_tours.seller_ad_campaigns.description',
            'group' => 'labels.seller_tours.groups.marketing',
            'permissions' => [
            'ad_campaign.view',
        ],
            'icon' => 'ti ti-compass',
            'stages' => [
            [
            'route' => 'seller.ads.campaigns.index',
            'steps' => [
            [
            'selector' => '#statusFilter',
            'title' => 'labels.seller_tours.seller_ad_campaigns.stage_0_step_0.title',
            'description' => 'labels.seller_tours.seller_ad_campaigns.stage_0_step_0.description',
        ],
            [
            'selector' => '#ad-campaigns-table',
            'title' => 'labels.seller_tours.seller_ad_campaigns.stage_0_step_1.title',
            'description' => 'labels.seller_tours.seller_ad_campaigns.stage_0_step_1.description',
        ],
            [
            'selector' => '#ad-campaigns-table_wrapper .dt-search, #ad-campaigns-table_wrapper .dataTables_filter',
            'title' => 'labels.seller_tours.seller_ad_campaigns.stage_0_step_2.title',
            'description' => 'labels.seller_tours.seller_ad_campaigns.stage_0_step_2.description',
            'optional' => true,
        ],
            [
            'selector' => '#ad-campaigns-table_wrapper .dt-length, #ad-campaigns-table_wrapper .dataTables_length',
            'title' => 'labels.seller_tours.seller_ad_campaigns.stage_0_step_3.title',
            'description' => 'labels.seller_tours.seller_ad_campaigns.stage_0_step_3.description',
            'optional' => true,
        ],
            [
            'selector' => '#ad-campaigns-table_wrapper .dt-paging, #ad-campaigns-table_wrapper .dataTables_paginate',
            'title' => 'labels.seller_tours.seller_ad_campaigns.stage_0_step_4.title',
            'description' => 'labels.seller_tours.seller_ad_campaigns.stage_0_step_4.description',
            'optional' => true,
        ],
        ],
        ],
            [
            'route' => 'seller.ads.campaigns.create',
            'steps' => [
            [
            'selector' => '#walletBalanceDisplay',
            'title' => 'labels.seller_tours.seller_ad_campaigns.stage_1_step_0.title',
            'description' => 'labels.seller_tours.seller_ad_campaigns.stage_1_step_0.description',
        ],
            [
            'selector' => '#select-product',
            'title' => 'labels.seller_tours.seller_ad_campaigns.stage_1_step_1.title',
            'description' => 'labels.seller_tours.seller_ad_campaigns.stage_1_step_1.description',
        ],
            [
            'selector' => '#budgetRange',
            'title' => 'labels.seller_tours.seller_ad_campaigns.stage_1_step_2.title',
            'description' => 'labels.seller_tours.seller_ad_campaigns.stage_1_step_2.description',
        ],
            [
            'selector' => '#estClicks',
            'title' => 'labels.seller_tours.seller_ad_campaigns.stage_1_step_3.title',
            'description' => 'labels.seller_tours.seller_ad_campaigns.stage_1_step_3.description',
        ],
            [
            'selector' => '#adPreviewWrap',
            'title' => 'labels.seller_tours.seller_ad_campaigns.stage_1_step_4.title',
            'description' => 'labels.seller_tours.seller_ad_campaigns.stage_1_step_4.description',
        ],
        ],
            'permission' => 'ad_campaign.create',
        ],
        ],
            'feature' => 'ads',
        ],
            'seller_ad_wallet' => [
            'title' => 'labels.seller_tours.seller_ad_wallet.title',
            'description' => 'labels.seller_tours.seller_ad_wallet.description',
            'group' => 'labels.seller_tours.groups.marketing',
            'permissions' => [
            'ad_wallet.view',
        ],
            'icon' => 'ti ti-compass',
            'stages' => [
            [
            'route' => 'seller.ads.wallet.index',
            'steps' => [
            [
            'selector' => '[data-seller-tour=\'ad-wallet\']',
            'title' => 'labels.seller_tours.seller_ad_wallet.stage_0_step_0.title',
            'description' => 'labels.seller_tours.seller_ad_wallet.stage_0_step_0.description',
        ],
            [
            'selector' => '[data-seller-tour=\'ad-wallet\'] .display-5',
            'title' => 'labels.seller_tours.seller_ad_wallet.stage_0_step_1.title',
            'description' => 'labels.seller_tours.seller_ad_wallet.stage_0_step_1.description',
        ],
            [
            'selector' => '[data-seller-tour=\'ad-wallet\'] a[data-seller-tour=\'ad-topup\']',
            'title' => 'labels.seller_tours.seller_ad_wallet.stage_0_step_2.title',
            'description' => 'labels.seller_tours.seller_ad_wallet.stage_0_step_2.description',
            'permission' => 'ad_wallet.topup',
            'optional' => true,
        ],
            [
            'selector' => '[data-seller-tour=\'ad-wallet\'] a[data-seller-tour=\'ad-transactions\']',
            'title' => 'labels.seller_tours.seller_ad_wallet.stage_0_step_3.title',
            'description' => 'labels.seller_tours.seller_ad_wallet.stage_0_step_3.description',
        ],
        ],
        ],
            [
            'route' => 'seller.ads.wallet.transactions',
            'steps' => [
            [
            'selector' => '#typeFilter',
            'title' => 'labels.seller_tours.seller_ad_wallet.stage_1_step_0.title',
            'description' => 'labels.seller_tours.seller_ad_wallet.stage_1_step_0.description',
        ],
            [
            'selector' => '#ad-wallet-transactions-table',
            'title' => 'labels.seller_tours.seller_ad_wallet.stage_1_step_1.title',
            'description' => 'labels.seller_tours.seller_ad_wallet.stage_1_step_1.description',
        ],
            [
            'selector' => '#ad-wallet-transactions-table_wrapper .dt-search, #ad-wallet-transactions-table_wrapper .dataTables_filter',
            'title' => 'labels.seller_tours.seller_ad_wallet.stage_1_step_2.title',
            'description' => 'labels.seller_tours.seller_ad_wallet.stage_1_step_2.description',
            'optional' => true,
        ],
            [
            'selector' => '#ad-wallet-transactions-table_wrapper .dt-length, #ad-wallet-transactions-table_wrapper .dataTables_length',
            'title' => 'labels.seller_tours.seller_ad_wallet.stage_1_step_3.title',
            'description' => 'labels.seller_tours.seller_ad_wallet.stage_1_step_3.description',
            'optional' => true,
        ],
            [
            'selector' => '#ad-wallet-transactions-table_wrapper .dt-paging, #ad-wallet-transactions-table_wrapper .dataTables_paginate',
            'title' => 'labels.seller_tours.seller_ad_wallet.stage_1_step_4.title',
            'description' => 'labels.seller_tours.seller_ad_wallet.stage_1_step_4.description',
            'optional' => true,
        ],
        ],
        ],
        ],
            'feature' => 'ads',
        ],
            'seller_watch_profile' => [
            'title' => 'labels.seller_tours.seller_watch_profile.title',
            'description' => 'labels.seller_tours.seller_watch_profile.description',
            'group' => 'labels.seller_tours.groups.marketing',
            'permissions' => [
            'watch_buy.profile.view',
        ],
            'icon' => 'ti ti-compass',
            'stages' => [
            [
            'route' => 'seller.watch-and-buy.profile.edit',
            'steps' => [
            [
            'selector' => '[data-seller-tour=\'watch-profile\']',
            'title' => 'labels.seller_tours.seller_watch_profile.stage_0_step_0.title',
            'description' => 'labels.seller_tours.seller_watch_profile.stage_0_step_0.description',
        ],
            [
            'selector' => '[name=\'username\']',
            'title' => 'labels.seller_tours.seller_watch_profile.stage_0_step_1.title',
            'description' => 'labels.seller_tours.seller_watch_profile.stage_0_step_1.description',
        ],
            [
            'selector' => '[name=\'bio\']',
            'title' => 'labels.seller_tours.seller_watch_profile.stage_0_step_2.title',
            'description' => 'labels.seller_tours.seller_watch_profile.stage_0_step_2.description',
        ],
            [
            'selector' => '[name=\'photo\']',
            'title' => 'labels.seller_tours.seller_watch_profile.stage_0_step_3.title',
            'description' => 'labels.seller_tours.seller_watch_profile.stage_0_step_3.description',
        ],
            [
            'selector' => '[data-seller-tour=\'watch-profile\'] button[type=\'submit\']',
            'title' => 'labels.seller_tours.seller_watch_profile.stage_0_step_4.title',
            'description' => 'labels.seller_tours.seller_watch_profile.stage_0_step_4.description',
            'permission' => 'watch_buy.profile.edit',
            'optional' => true,
        ],
        ],
        ],
        ],
            'feature' => 'watch',
        ],
            'seller_stories' => [
            'title' => 'labels.seller_tours.seller_stories.title',
            'description' => 'labels.seller_tours.seller_stories.description',
            'group' => 'labels.seller_tours.groups.marketing',
            'permissions' => [
            'watch_buy.status.view',
        ],
            'icon' => 'ti ti-compass',
            'stages' => [
            [
            'route' => 'seller.watch-and-buy.statuses.index',
            'steps' => [
            [
            'selector' => '.seller-watch-buy-statuses',
            'title' => 'labels.seller_tours.seller_stories.stage_0_step_0.title',
            'description' => 'labels.seller_tours.seller_stories.stage_0_step_0.description',
        ],
            [
            'selector' => '.watch-buy-status-section-empty, .watch-buy-status-pagination, .seller-watch-buy-statuses .card-body',
            'title' => 'labels.seller_tours.seller_stories.stage_0_step_1.title',
            'description' => 'labels.seller_tours.seller_stories.stage_0_step_1.description',
        ],
            [
            'selector' => '#seller-watch-buy-status-composer',
            'title' => 'labels.seller_tours.seller_stories.stage_0_step_2.title',
            'description' => 'labels.seller_tours.seller_stories.stage_0_step_2.description',
            'permission' => 'watch_buy.status.create',
            'optional' => true,
            'reveal' => [
            'type' => 'modal',
            'selector' => '#seller-watch-buy-status-composer',
        ],
        ],
            [
            'selector' => '#seller-watch-buy-status-composer input[type=\'file\']',
            'title' => 'labels.seller_tours.seller_stories.stage_0_step_3.title',
            'description' => 'labels.seller_tours.seller_stories.stage_0_step_3.description',
            'permission' => 'watch_buy.status.create',
            'optional' => true,
        ],
            [
            'selector' => '#seller-watch-buy-status-composer button[type=\'submit\']',
            'title' => 'labels.seller_tours.seller_stories.stage_0_step_4.title',
            'description' => 'labels.seller_tours.seller_stories.stage_0_step_4.description',
            'permission' => 'watch_buy.status.create',
            'optional' => true,
        ],
        ],
            'redirect' => 'seller.watch-and-buy.profile.edit',
        ],
        ],
            'feature' => 'stories',
        ],
            'seller_reels' => [
            'title' => 'labels.seller_tours.seller_reels.title',
            'description' => 'labels.seller_tours.seller_reels.description',
            'group' => 'labels.seller_tours.groups.marketing',
            'permissions' => [
            'watch_buy.reel.view',
        ],
            'icon' => 'ti ti-compass',
            'stages' => [
            [
            'route' => 'seller.watch-and-buy.reels.index',
            'steps' => [
            [
            'selector' => '#seller-watch-buy-reels-table',
            'title' => 'labels.seller_tours.seller_reels.stage_0_step_0.title',
            'description' => 'labels.seller_tours.seller_reels.stage_0_step_0.description',
        ],
            [
            'selector' => '#seller-watch-buy-reels-table_wrapper .dt-search, #seller-watch-buy-reels-table_wrapper .dataTables_filter',
            'title' => 'labels.seller_tours.seller_reels.stage_0_step_1.title',
            'description' => 'labels.seller_tours.seller_reels.stage_0_step_1.description',
            'optional' => true,
        ],
            [
            'selector' => '#seller-watch-buy-reels-table_wrapper .dt-length, #seller-watch-buy-reels-table_wrapper .dataTables_length',
            'title' => 'labels.seller_tours.seller_reels.stage_0_step_2.title',
            'description' => 'labels.seller_tours.seller_reels.stage_0_step_2.description',
            'optional' => true,
        ],
            [
            'selector' => '#seller-watch-buy-reels-table_wrapper .dt-paging, #seller-watch-buy-reels-table_wrapper .dataTables_paginate',
            'title' => 'labels.seller_tours.seller_reels.stage_0_step_3.title',
            'description' => 'labels.seller_tours.seller_reels.stage_0_step_3.description',
            'optional' => true,
        ],
        ],
            'redirect' => 'seller.watch-and-buy.profile.edit',
        ],
            [
            'route' => 'seller.watch-and-buy.reels.create',
            'steps' => [
            [
            'selector' => '[data-seller-tour=\'reel-form\']',
            'title' => 'labels.seller_tours.seller_reels.stage_1_step_0.title',
            'description' => 'labels.seller_tours.seller_reels.stage_1_step_0.description',
        ],
            [
            'selector' => '[name=\'caption\']',
            'title' => 'labels.seller_tours.seller_reels.stage_1_step_1.title',
            'description' => 'labels.seller_tours.seller_reels.stage_1_step_1.description',
        ],
            [
            'selector' => '[name=\'video\']',
            'title' => 'labels.seller_tours.seller_reels.stage_1_step_2.title',
            'description' => 'labels.seller_tours.seller_reels.stage_1_step_2.description',
        ],
            [
            'selector' => '[name=\'cover\']',
            'title' => 'labels.seller_tours.seller_reels.stage_1_step_3.title',
            'description' => 'labels.seller_tours.seller_reels.stage_1_step_3.description',
        ],
            [
            'selector' => '[data-seller-tour=\'reel-products\']',
            'title' => 'labels.seller_tours.seller_reels.stage_1_step_4.title',
            'description' => 'labels.seller_tours.seller_reels.stage_1_step_4.description',
            'optional' => true,
        ],
            [
            'selector' => '[data-seller-tour=\'reel-form\'] button[type=\'submit\']',
            'title' => 'labels.seller_tours.seller_reels.stage_1_step_5.title',
            'description' => 'labels.seller_tours.seller_reels.stage_1_step_5.description',
        ],
        ],
            'permission' => 'watch_buy.reel.create',
            'redirect' => 'seller.watch-and-buy.profile.edit',
        ],
        ],
            'feature' => 'watch',
        ],
            'seller_roles' => [
            'title' => 'labels.seller_tours.seller_roles.title',
            'description' => 'labels.seller_tours.seller_roles.description',
            'group' => 'labels.seller_tours.groups.team',
            'permissions' => [
            'role.view',
        ],
            'icon' => 'ti ti-compass',
            'stages' => [
            [
            'route' => 'seller.roles.index',
            'steps' => [
            [
            'selector' => '#roles-table',
            'title' => 'labels.seller_tours.seller_roles.stage_0_step_0.title',
            'description' => 'labels.seller_tours.seller_roles.stage_0_step_0.description',
        ],
            [
            'selector' => '#roles-table_wrapper .dt-search, #roles-table_wrapper .dataTables_filter',
            'title' => 'labels.seller_tours.seller_roles.stage_0_step_1.title',
            'description' => 'labels.seller_tours.seller_roles.stage_0_step_1.description',
            'optional' => true,
        ],
            [
            'selector' => '#roles-table_wrapper .dt-length, #roles-table_wrapper .dataTables_length',
            'title' => 'labels.seller_tours.seller_roles.stage_0_step_2.title',
            'description' => 'labels.seller_tours.seller_roles.stage_0_step_2.description',
            'optional' => true,
        ],
            [
            'selector' => '#roles-table_wrapper .dt-paging, #roles-table_wrapper .dataTables_paginate',
            'title' => 'labels.seller_tours.seller_roles.stage_0_step_3.title',
            'description' => 'labels.seller_tours.seller_roles.stage_0_step_3.description',
            'optional' => true,
        ],
            [
            'selector' => '#role-modal [name=\'name\']',
            'title' => 'labels.seller_tours.seller_roles.stage_0_step_4.title',
            'description' => 'labels.seller_tours.seller_roles.stage_0_step_4.description',
            'permission' => 'role.create',
            'optional' => true,
            'reveal' => [
            'type' => 'modal',
            'selector' => '#role-modal',
        ],
        ],
        ],
        ],
            [
            'route' => 'seller.roles.permissions.index',
            'selection' => 'select_permissions',
            'steps' => [
            [
            'selector' => '[data-seller-tour=\'permissions\']',
            'title' => 'labels.seller_tours.seller_roles.stage_1_step_0.title',
            'description' => 'labels.seller_tours.seller_roles.stage_1_step_0.description',
        ],
            [
            'selector' => '[data-seller-tour=\'permissions\'] .form-label',
            'title' => 'labels.seller_tours.seller_roles.stage_1_step_1.title',
            'description' => 'labels.seller_tours.seller_roles.stage_1_step_1.description',
        ],
            [
            'selector' => '[data-seller-tour=\'permissions\'] .permission-checkbox',
            'title' => 'labels.seller_tours.seller_roles.stage_1_step_2.title',
            'description' => 'labels.seller_tours.seller_roles.stage_1_step_2.description',
        ],
            [
            'selector' => '[data-seller-tour=\'permissions\'] button[type=\'submit\']',
            'title' => 'labels.seller_tours.seller_roles.stage_1_step_3.title',
            'description' => 'labels.seller_tours.seller_roles.stage_1_step_3.description',
            'permission' => 'role.permission.edit',
            'optional' => true,
        ],
        ],
            'record' => true,
            'listing' => 'seller.roles.index',
            'permission' => 'role.permission.view',
        ],
        ],
        ],
            'seller_staff' => [
            'title' => 'labels.seller_tours.seller_staff.title',
            'description' => 'labels.seller_tours.seller_staff.description',
            'group' => 'labels.seller_tours.groups.team',
            'permissions' => [
            'system_user.view',
        ],
            'icon' => 'ti ti-compass',
            'stages' => [
            [
            'route' => 'seller.system-users.index',
            'steps' => [
            [
            'selector' => '#system-user-table',
            'title' => 'labels.seller_tours.seller_staff.stage_0_step_0.title',
            'description' => 'labels.seller_tours.seller_staff.stage_0_step_0.description',
        ],
            [
            'selector' => '#system-user-table_wrapper .dt-search, #system-user-table_wrapper .dataTables_filter',
            'title' => 'labels.seller_tours.seller_staff.stage_0_step_1.title',
            'description' => 'labels.seller_tours.seller_staff.stage_0_step_1.description',
            'optional' => true,
        ],
            [
            'selector' => '#system-user-table_wrapper .dt-length, #system-user-table_wrapper .dataTables_length',
            'title' => 'labels.seller_tours.seller_staff.stage_0_step_2.title',
            'description' => 'labels.seller_tours.seller_staff.stage_0_step_2.description',
            'optional' => true,
        ],
            [
            'selector' => '#system-user-table_wrapper .dt-paging, #system-user-table_wrapper .dataTables_paginate',
            'title' => 'labels.seller_tours.seller_staff.stage_0_step_3.title',
            'description' => 'labels.seller_tours.seller_staff.stage_0_step_3.description',
            'optional' => true,
        ],
            [
            'selector' => '#system-user-modal [name=\'name\']',
            'title' => 'labels.seller_tours.seller_staff.stage_0_step_4.title',
            'description' => 'labels.seller_tours.seller_staff.stage_0_step_4.description',
            'permission' => 'system_user.create',
            'optional' => true,
            'reveal' => [
            'type' => 'modal',
            'selector' => '#system-user-modal',
        ],
        ],
            [
            'selector' => '#system-user-modal #select-roles',
            'title' => 'labels.seller_tours.seller_staff.stage_0_step_5.title',
            'description' => 'labels.seller_tours.seller_staff.stage_0_step_5.description',
            'permission' => 'system_user.create',
            'optional' => true,
        ],
            [
            'selector' => '#system-user-modal button[type=\'submit\']',
            'title' => 'labels.seller_tours.seller_staff.stage_0_step_6.title',
            'description' => 'labels.seller_tours.seller_staff.stage_0_step_6.description',
            'permission' => 'system_user.create',
            'optional' => true,
        ],
        ],
        ],
        ],
        ],
            'seller_notifications' => [
            'title' => 'labels.seller_tours.seller_notifications.title',
            'description' => 'labels.seller_tours.seller_notifications.description',
            'group' => 'labels.seller_tours.groups.team',
            'permissions' => [
            'notification.view',
        ],
            'icon' => 'ti ti-compass',
            'stages' => [
            [
            'route' => 'seller.notifications.index',
            'steps' => [
            [
            'selector' => '#notifications-table',
            'title' => 'labels.seller_tours.seller_notifications.stage_0_step_0.title',
            'description' => 'labels.seller_tours.seller_notifications.stage_0_step_0.description',
        ],
            [
            'selector' => '#notifications-table_wrapper .dt-search, #notifications-table_wrapper .dataTables_filter',
            'title' => 'labels.seller_tours.seller_notifications.stage_0_step_1.title',
            'description' => 'labels.seller_tours.seller_notifications.stage_0_step_1.description',
            'optional' => true,
        ],
            [
            'selector' => '#notifications-table_wrapper .dt-length, #notifications-table_wrapper .dataTables_length',
            'title' => 'labels.seller_tours.seller_notifications.stage_0_step_2.title',
            'description' => 'labels.seller_tours.seller_notifications.stage_0_step_2.description',
            'optional' => true,
        ],
            [
            'selector' => '#notifications-table_wrapper .dt-paging, #notifications-table_wrapper .dataTables_paginate',
            'title' => 'labels.seller_tours.seller_notifications.stage_0_step_3.title',
            'description' => 'labels.seller_tours.seller_notifications.stage_0_step_3.description',
            'optional' => true,
        ],
            [
            'selector' => '#mark-all-read-btn',
            'title' => 'labels.seller_tours.seller_notifications.stage_0_step_4.title',
            'description' => 'labels.seller_tours.seller_notifications.stage_0_step_4.description',
            'permission' => 'notification.edit',
            'optional' => true,
        ],
            [
            'selector' => '#viewNotificationModal',
            'title' => 'labels.seller_tours.seller_notifications.stage_0_step_5.title',
            'description' => 'labels.seller_tours.seller_notifications.stage_0_step_5.description',
            'optional' => true,
        ],
        ],
        ],
        ],
        ],
        ];
