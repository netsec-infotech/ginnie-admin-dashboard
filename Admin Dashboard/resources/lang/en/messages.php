<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user. You are free to modify
    | these language lines according to your application's requirements.
    |
    */

    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'maximum_items_allowed_in_cart_reached' => 'Maximum items allowed in cart reached.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',
    'verify_store_info' => 'To verify a store, simply click the Eye icon from the Store table.',
    'quantity_step_size_gte_minimum_order_quantity' => 'The quantity step size must be greater than or equal to the minimum order quantity.',
    'quantity_step_size_lte_total_allowed_quantity' => 'The quantity step size must be less than or equal to the total allowed quantity.',
    'minimum_order_quantity_lte_total_allowed_quantity' => 'The minimum order quantity must be less than or equal to the total allowed quantity.',
    'google_api_key_not_found' => 'Google API Key Not Found, Please Add It From Settings > Authentication > Google API Key',
    'created_successfully' => 'Delivery zone created successfully.',
    'creation_error' => 'An error occurred while creating the delivery zone.',
    'invalid_boundary_json' => 'Invalid boundary JSON format.',
    'internal_server_error' => 'Internal server error',
    'delivery_zone_created_successfully' => 'Delivery zone created successfully.',
    'delivery_zone_retrieved_successfully' => 'Delivery zone retrieved successfully.',
    'slots_retrieved_successfully' => 'Delivery time slots retrieved successfully.',
    'slot_cutoff_passed' => 'Cutoff time passed for this slot.',
    'delivery_zone_not_found' => 'Delivery zone not found.',
    'delivery_zone_found' => 'Delivery zone found successfully.',
    'delivery_zones_found' => 'Delivery zones retrieved successfully.',
    'delivery_zone_updated_successfully' => 'Delivery zone updated successfully.',
    'delivery_zone_deleted_successfully' => 'Delivery zone deleted successfully.',
    'delivery_zone_creation_error' => 'Failed to create delivery zone.',
    'delivery_zone_update_error' => 'Failed to update delivery zone.',
    'delivery_zone_deletion_error' => 'Failed to delete delivery zone.',
    'error_creating_delivery_zone' => 'Error creating delivery zone',
    'error_updating_delivery_zone' => 'Error updating delivery zone',
    'error_deleting_delivery_zone' => 'Error deleting delivery zone',

    'latitude_required' => 'The latitude field is required.',
    'latitude_numeric' => 'The latitude must be a number.',
    'latitude_between' => 'The latitude must be between -90 and 90.',
    'longitude_required' => 'The longitude field is required.',
    'longitude_numeric' => 'The longitude must be a number.',
    'longitude_between' => 'The longitude must be between -180 and 180.',
    'delivery_zone_overlap_error' => 'This delivery zone overlaps with existing zones.',
    'delivery_zone_creation_failed' => 'Failed to create delivery zone',
    'delivery_zone_update_failed' => 'Failed to update delivery zone',
    'delivery_time_per_km_info_message' => 'Average time (in minutes) needed to deliver per kilometer in this zone. Used to estimate total delivery time based on distance.',
    'buffer_time_info_message' => 'Extra time (in minutes) added to account for delays like traffic or weather.',
    'rush_delivery_enabled_info_message' => 'Enable or disable rush delivery option for this zone. Rush delivery provides faster delivery at a premium price.',
    'rush_delivery_time_per_km_info_message' => 'Average time (in minutes) needed to deliver per kilometer for rush deliveries. This is typically faster than regular delivery time.',
    'rush_delivery_charges_info_message' => 'Additional charges applied for rush delivery service. This is a premium fee for faster delivery.',
    'regular_delivery_charges_info_message' => 'Standard delivery charges applied for normal delivery service in this zone.',
    'free_delivery_amount_info_message' => 'Minimum order amount required for free delivery. Orders above this amount will have delivery charges waived.',
    'distance_based_delivery_charges_info_message' => 'Additional charges applied per kilometer for delivery. This is added to the regular delivery charges based on distance.',
    'per_store_drop_off_fee_info_message' => 'Additional fee charged for each store in a multi-store pickup order. This compensates delivery personnel for multiple stops.',
    'handling_charges_info_message' => 'Administrative fee that goes to the admin for processing the order. This is separate from delivery charges and is applied to all orders.',
    'delivery_boy_base_fee_info_message' => 'Flat base earning paid to the delivery boy for completing an order, before adding any per-store or distance-based fees.',
    'delivery_boy_per_store_pickup_fee_info_message' => 'Extra earning credited to the delivery boy for each store they pick up from on a multi-store order.',
    'delivery_boy_distance_based_fee_info_message' => 'Earning calculated per kilometer of the delivery distance and paid to the delivery boy on top of the base fee.',
    'delivery_boy_per_order_incentive_info_message' => 'Bonus paid to the delivery boy for every successful order, in addition to the base and distance fees.',

    // Subscription plan tooltips
    'subscription_plan_price_info_message' => 'One-time price the seller pays to activate this plan. Ignored when "Is Free" is enabled.',
    'subscription_plan_duration_type_info_message' => 'Choose "Unlimited" for plans that never expire, or "Days" to set a fixed validity period.',
    'subscription_plan_duration_days_info_message' => 'Number of days the plan stays active after a seller subscribes. Required only when duration type is set to "Days".',
    'subscription_plan_is_free_info_message' => 'When enabled, sellers can subscribe to this plan without payment. Useful for trial or starter plans.',
    'subscription_plan_is_recommended_info_message' => 'Highlights this plan as recommended on the seller-facing pricing page so it stands out from other options.',
    'subscription_plan_configurations_info_message' => 'Per-feature usage limits for sellers on this plan (max products, stores, addon groups, etc.). Leave a value empty to grant unlimited access for that feature.',

    // Product form tooltips
    'product_hsn_code_info_message' => 'Tax classification code (HSN/SAC for India, similar codes elsewhere). Used by the tax engine to apply the correct tax rate.',
    'product_indicator_info_message' => 'Visual badge shown to customers (e.g. veg, non-veg, eggless). Helps shoppers identify the product at a glance.',
    'product_base_prep_time_info_message' => 'Time the store needs to prepare or pack this product before handing it off for delivery. Added to the zone delivery time when computing the customer ETA.',
    'product_minimum_order_quantity_info_message' => 'Smallest quantity a customer can buy in a single order. Defaults to 1 if left blank.',
    'product_quantity_step_size_info_message' => 'Increment used by the quantity selector. For example, set 5 if customers must order in multiples of 5.',
    'product_total_allowed_quantity_info_message' => 'Maximum quantity a single customer can order at once. Leave 0 or empty for no upper limit.',
    'product_cancelable_till_info_message' => 'Latest order stage at which a customer can still cancel. Past this status, the cancel button disappears.',
    'product_is_attachment_required_info_message' => 'Force the customer to upload a file (e.g. prescription, design proof) before they can place the order.',
    'product_attachment_mode_info_message' => 'Decides whether the attachment is required per item or once per order.',
    'product_featured_info_message' => 'Featured products show up in dedicated "Featured" carousels on the storefront and home page.',
    'product_recommended_info_message' => 'Recommended products show a "Recommended" badge in the customer-facing UI and can be fetched through the recommended API filter.',
    'product_image_fit_info_message' => 'Controls how the main image is displayed: "cover" fills the frame and may crop, "contain" shows the full image with empty bars.',
    'product_tax_group_info_message' => 'Select one or more tax groups (e.g. GST, VAT) that should apply to this product when checking out.',
    'product_is_inclusive_tax_info_message' => 'When enabled, the prices you enter already include tax. When disabled, tax is added on top at checkout.',

    // Tax tooltips
    'tax_rate_percentage_info_message' => 'Percentage rate applied to the taxable amount. Use 5 for 5%, 18 for 18%, etc.',
    'tax_group_sub_taxes_info_message' => 'Combine multiple individual tax rates (e.g. CGST + SGST) into one group that can be assigned to products.',

    // Promo tooltips
    'promo_discount_type_info_message' => 'Choose "percentage" to discount by % of the cart, or "fixed" to deduct a flat amount.',
    'promo_discount_amount_info_message' => 'For percentage discounts, enter a number between 1 and 100. For fixed discounts, enter the amount in your currency.',
    'promo_max_discount_value_info_message' => 'Caps the discount when using a percentage type so a single order cannot exceed this amount.',
    'promo_min_order_total_info_message' => 'Minimum cart total a customer must reach for the promo to apply. Orders below this value will not see the discount.',
    'promo_mode_info_message' => 'Instant: discount is applied at checkout. Cashback: full amount is charged and the discount is credited to the wallet after delivery.',
    'promo_max_total_usage_info_message' => 'Total number of times this promo can be redeemed across all customers combined. Useful for limited-time campaigns.',
    'promo_max_usage_per_user_info_message' => 'Maximum times a single customer can use this promo. Set to 1 for first-order-only style offers.',

    // Store form tooltips
    'store_address_proof_info_message' => 'Upload a recent utility bill, lease agreement, or government-issued document that confirms the store address.',
    'store_voided_check_info_message' => 'Upload a cancelled cheque or bank passbook page so payouts can be verified against the bank account below.',
    'store_tax_name_info_message' => 'Name of the tax registration body (e.g. GSTIN, VAT, EIN) used for invoicing.',
    'store_tax_number_info_message' => 'Tax registration number issued by the authority above. Shown on customer invoices and used for compliance.',
    'store_bank_branch_code_info_message' => 'Branch identifier for your bank account (IFSC in India, SWIFT/BIC for international, sort code in the UK, routing for the US).',
    'store_routing_number_info_message' => 'Bank routing or transit number used to direct payouts. Required for ACH/wire transfers in some regions.',
    'store_bank_account_type_info_message' => 'Pick the account category (savings, current, checking) that matches the account you entered above.',

    // Addon group tooltips
    'addon_selection_type_info_message' => '"Single" lets the customer pick only one option from the group. "Multiple" allows several add-ons to be selected together.',
    'sort_order_info_message' => 'Lower numbers appear first. Use this to control how addon groups are ordered on the product page.',

    // Withdrawal tooltips
    'available_for_withdrawal_info_message' => 'Wallet balance you can request a payout for right now (total balance minus any blocked amount).',
    'blocked_balance_info_message' => 'Funds temporarily held back, usually because they are tied to ongoing orders, returns, or pending settlements.',
    'withdrawal_amount_info_message' => 'Amount you want to receive. Cannot exceed the available balance and is subject to the minimum withdrawal limit configured by the admin.',

    // Banner tooltips
    'banner_scope_type_info_message' => 'Pick "Global" to show the banner across the whole storefront, or "Category" to limit it to a specific category page.',
    'banner_type_info_message' => 'Defines what the banner links to when tapped: a product, category, brand, or a custom URL.',
    'banner_position_info_message' => 'Determines where on the storefront the banner is rendered (e.g. top hero, mid-page strip, sidebar).',
    'banner_visibility_status_info_message' => '"Draft" keeps the banner hidden while you finish editing. "Published" makes it visible to customers.',
    'banner_display_order_info_message' => 'Lower numbers appear first when multiple banners share the same position.',
    'banner_custom_url_info_message' => 'Full URL the banner should open. Use this only when the banner type is set to "Custom".',

    // Brand tooltips
    'brand_scope_type_info_message' => 'Choose "Global" to make the brand available across all categories, or "Category" to scope it to one category tree.',

    // Category tooltips
    'category_parent_info_message' => 'Pick a parent to nest this category under it. Leave empty to create a top-level (root) category.',
    'category_icon_info_message' => 'Small icon shown next to the category name in menus and listings. PNG/SVG with a transparent background works best.',
    'category_active_icon_info_message' => 'Alternate icon shown when this category is the currently selected/active one in the navigation.',
    'category_background_type_info_message' => 'Choose how this category card is styled on the home page: a flat color or a background image.',
    'category_commission_info_message' => 'Default platform commission percentage for products under this category. Used when no seller-specific override is set.',
    'category_requires_approval_info_message' => 'When enabled, products created under this category stay in pending state until an admin approves them.',
    'category_cannot_be_deleted_with_products' => 'Category cannot be deleted as there are active products under this category',

    // Featured section tooltips
    'featured_section_scope_type_info_message' => 'Pick "Global" to show this section on the main home feed, or "Category" to surface it only on a specific category page.',
    'featured_section_type_info_message' => 'Decides what the section displays: products, categories, brands, banners, etc.',
    'featured_section_style_info_message' => 'Visual layout used to render the section (e.g. carousel, grid, full-width hero).',
    // Cart Success Messages
    'item_added_to_cart_successfully' => 'Item added to cart successfully',
    'item_removed_from_cart_successfully' => 'Item removed from cart successfully',
    'cart_item_quantity_updated_successfully' => 'Cart item quantity updated successfully',
    'cart_cleared_successfully' => 'Cart cleared successfully',
    'cart_retrieved_successfully' => 'Cart retrieved successfully',
    'cart_updated_based_on_location' => 'Cart updated based on your location. Some items were removed as they are not available for delivery to your area',
    'cart_location_verified' => 'Cart location verified. All items are available for delivery to your location',

    // Cart Error Messages
    'cart_is_empty' => 'Your cart is empty',
    'cart_item_not_found' => 'Cart item not found',
    'product_variant_not_available_in_store' => 'Product variant is not available in the selected store',
    'insufficient_stock_available' => 'Insufficient stock available',
    'store_offline_cannot_add_to_cart' => 'This store is currently offline. You cannot add items from this store to your cart right now.',
    'store_offline_cannot_place_order' => 'One or more stores in your cart are currently offline (:stores). Please remove those items to proceed with your order.',
    'store_closed_cannot_place_order' => 'One or more stores in your cart are currently closed (:stores). Please remove those items or try again when the store is open.',
    'store_temporarily_closed' => 'Store is temporarily closed.',
    'store_not_open_now' => 'Store is outside its operating hours.',
    'invalid_operating_hours' => 'Invalid operating hours configured for this store.',
    'pickup_not_enabled_globally' => 'Customer pickup is not enabled globally.',
    'stores_do_not_support_pickup' => 'To place a pickup order, please remove items from the following stores from your cart or select delivery instead: :stores',
    'pickup_no_cod' => 'Pickup orders cannot use Cash on Delivery (COD) payment method.',
    'order_ready_for_pickup_successfully' => 'Order is ready for pickup.',
    'failed_to_mark_ready' => 'Failed to mark order ready for pickup.',
    'too_many_otp_attempts' => 'Too many invalid OTP attempts. Locked for 5 minutes.',
    'invalid_otp' => 'Invalid OTP code.',
    'order_picked_up_successfully' => 'Order picked up successfully.',
    'pickup_verification_failed' => 'Pickup verification failed.',

    // Cart quantity validations
    'quantity_must_be_multiple_of_step_size' => 'The quantity must be a multiple of :step.',
    'quantity_must_be_at_least_minimum_order_quantity' => 'The quantity must be at least the minimum order quantity of :min.',
    'quantity_must_not_exceed_total_allowed_quantity' => 'The quantity must not exceed the total allowed quantity of :max.',

    // General Messages
    'something_went_wrong' => 'Something went wrong. Please try again',
    'invalid_coordinates' => 'Invalid coordinates! Please Select A valid Address',
    'required' => 'This field is required.',
    'integer' => 'This field must be an integer.',
    'string' => 'This field must be a string.',
    'max' => 'This field exceeds the maximum allowed length.',
    'min' => 'This field is below the minimum allowed value.',

    // Product validation
    'product_id_required' => 'The product field is required.',
    'product_not_found' => 'The selected product does not exist.',

    // Rating validation
    'rating_required' => 'Rating is required.',
    'rating_must_be_integer' => 'Rating must be an integer value.',
    'rating_must_be_at_least_1' => 'Rating must be at least 1.',
    'rating_must_not_exceed_5' => 'Rating must not exceed 5.',

    // Title validation
    'title_required' => 'A title is required.',
    'title_max_length' => 'The title may not be greater than 255 characters.',

    // Comment validation
    'comment_max_length' => 'The comment may not be greater than 1000 characters.',

    // Description validation
    'description_required' => 'A description is required.',
    'description_max_length' => 'The description may not be greater than 1000 characters.',

    // Seller validation
    'seller_id_required' => 'The seller field is required.',
    'seller_not_found' => 'The selected seller does not exist.',

    // Order Messages
    'invalid_payment_type' => 'Invalid payment type',
    'order_created_successfully' => 'Order created successfully',
    'order_not_found' => 'Order not found',
    'order_retrieved_successfully' => 'Order retrieved successfully',
    'orders_retrieved_successfully' => 'Orders retrieved successfully',

    // Store status toggle
    'store_status_updated_successfully' => 'Store status updated successfully.',
    'store_status_update_failed' => 'Failed to update store status.',
    'store_not_found' => 'Store not found.',

    // Seller Order Messages
    'order_status_updated_successfully' => 'Order status updated successfully',
    'unauthorized_action' => 'You are not authorized to perform this action',
    'order_status_update_failed' => 'Failed to update order status',
    'status_already_set' => 'The order item already has this status',

    // Language Names
    'languages' => [
        'english' => 'English',
        'spanish' => 'Spanish',
        'french' => 'French',
        'german' => 'German',
        'chinese' => 'Chinese',
    ],

    // Dashboard Messages
    'cannot_delete_delivery_zone_has_delivery_boys' => 'Cannot delete delivery zone as it has associated delivery',
    'require_otp_before_delivery' => 'If you enable this option, OTP verification will be required before delivering the product to the customer.',
    'discount_amount_percent_or_amount' => 'If Discount Type is Percentage, then Discount Amount should be between 0 and 100. If Discount Type is Flet, then Discount Amount should be greater than 0.',
    'max_discount_amount_must_be_greater_than_discount_amount' => 'Max Discount Amount must be greater than Discount Amount.',
    'discount_amount_exceeds_min_order_total' => 'Discount Amount cannot exceed the Minimum Order Total.',

    // Promo Validation Messages
    'promo_code_required' => 'The promo code is required.',
    'promo_code_unique' => 'This promo code already exists.',
    'start_date_required' => 'The start date is required.',
    'start_date_after_or_equal' => 'The start date must be today or later.',
    'end_date_required' => 'The end date is required.',
    'end_date_after' => 'The end date must be after the start date.',
    'discount_type_required' => 'The discount type is required.',
    'discount_type_in' => 'The discount type must be either percentage or fixed.',
    'discount_amount_required' => 'The discount amount is required.',
    'discount_amount_min' => 'The discount amount must be at least 0.',
    'percentage_discount_max' => 'Percentage discount cannot be more than 100%.',
    'max_discount_value_required_for_percentage' => 'Maximum discount value is required for percentage discounts.',

    // Promo Code Application Messages
    'invalid_promo_code' => 'Invalid promo code.',
    'promo_code_expired' => 'This promo code has expired.',
    'promo_code_not_yet_active' => 'This promo code is not yet active.',
    'minimum_order_amount_not_met' => 'Minimum order amount of :amount is required to use this promo code.',
    'promo_code_usage_limit_exceeded' => 'This promo code has reached its usage limit.',
    'promo_code_user_limit_exceeded' => 'You have reached the maximum usage limit for this promo code.',
    'promo_code_applied_successfully' => 'Promo code applied successfully.',
    'promo_code_validation_error' => 'An error occurred while validating the promo code.',
    'order_amount_required' => 'Order amount is required for promo code validation.',
    'promos_retrieved_successfully' => 'Available promos retrieved successfully.',
    'cart_amount_required' => 'Cart amount is required for promo code validation.',
    'delivery_charge_required' => 'Delivery charge is required for promo code validation.',
    // Business Document Upload Notes
    'business_license_note' => 'Upload a clear copy of your business license. Accepted formats: JPEG, PNG, PDF. Max size: 2MB.',
    'articles_of_incorporation_note' => 'Provide your company\'s articles of incorporation or certificate of incorporation. File must be clear and readable.',
    'national_identity_card_note' => 'Upload a government-issued photo ID (passport, driver\'s license, or national ID card). Both front and back sides if applicable.',
    'authorized_signature_note' => 'Upload a document with authorized signature samples or signature authorization letter from your company.',

    // Order Item Cancellation Messages
    'order_item_not_found' => 'Order item not found.',
    'product_not_cancelable' => 'This product cannot be cancelled.',
    'order_item_cannot_be_cancelled_at_current_status' => 'Order item cannot be cancelled at its current status.',
    'order_item_already_in_terminal_state' => 'Order item is already in a terminal state and cannot be cancelled.',
    'order_item_cancelled_successfully' => 'Order item cancelled successfully.',
    'refund_processing_failed' => 'Failed to process refund.',
    'refund_processed_successfully' => 'Refund processed successfully.',

    'product_not_returnable' => 'This product is not eligible for return.',
    'order_item_cannot_be_returned_at_current_status' => 'This order item cannot be returned in its current status.',
    'return_already_requested' => 'A return request for this item already exists.',
    'return_request_created' => 'Your return request has been submitted successfully.',
    'return_request_sent' => 'Your return request has been sent successfully.',

    'return_request_not_found' => 'Return request not found.',
    'return_cannot_be_cancelled_now' => 'This return request can no longer be cancelled.',
    'return_request_cancelled' => 'Your return request has been successfully cancelled.',
    'return_approved_successfully' => 'Return approved successfully',
    'return_rejected_successfully' => 'Return rejected successfully',
    'return_not_found' => 'Return not found.',
    'order_item_id_required' => 'Order item ID is required.',
    'cashback_info_message' => 'Cashback will be credited once the order is delivered and the return period is completed.',
    'category_cannot_be_deactivated_with_products' => "Category can't be deactivated because it contains products.",
    'service_account_file_description' => 'The service file is stored in a private directory, so it cannot be accessed directly via URL. If you try to open it in the browser, it will show a 403 Forbidden error. This is expected behavior and there is no issue with the setup.',
    'select_zone_message' => 'Your store location must be within our available delivery areas. Please select a location inside the blue highlighted zones on the map.',
    'cron_detected' => 'Detected. The log file exists at :path.',
    'cron_log_last_updated' => 'The log file exists at :path.',

    'cron_not_detected_full' => 'Notifications are currently not functioning because the required cron job has not been configured on the server.',
    'subscription_cron_not_detected_full' => 'Subscription expiry processing is currently not functioning because the required scheduler command has not been configured on the server.',

    'add_cron_instruction' => 'Add the following cron entry on your server to process queued notifications. Output is redirected to a log file so you can verify whether the cron is running.',

    'php_path_note' => 'Note: If your PHP binary path differs from /usr/bin/php, adjust it accordingly.',

    'view_documentation' => 'For more details on configuring notifications, please refer to our documentation.',
    'log_file_not_found' => 'The log file was not found at',
    'cron_has_not_run_yet' => 'This likely means the cron job has not been added or has not executed yet.',
    'custom_app_schema_regex_message' => 'Spaces are not allowed in the app schema.',
    'add_subscription_expire_instruction' => 'Add the following cron to your server to process subscription expire. Output is redirected to a log file so you can verify whether the cron is running.',
    'subscription_expiry_scheduler_instruction' => 'Add the following scheduler command on your server. The scheduler will run every hour to update and expire expired subscriptions automatically. Output is redirected to a log file so you can verify that the scheduler is running.',

    // Cart — addon selection errors
    'addon_not_available_for_variant' => 'The selected add-on is not available for this product variant at this store.',
    'addon_group_single_selection_required' => 'Only one option may be selected from the ":group" add-on group.',
    'addon_group_required_missing' => 'Please select an option from the required ":group" add-on group.',
    'addon_item_unavailable' => 'The selected add-on item is currently unavailable.',
    'addon_item_insufficient_stock' => 'The selected add-on item does not have enough stock available.',
    'pickup_return_physical_only' => 'To return this item, please visit the store where the order was collected.',

    // Return messages
    'return_receipt_confirmed_successfully' => 'Return item receipt confirmed and refund processed.',
    'return_receipt_rejected_successfully' => 'Return item rejected at store.',
    'return_already_processed' => 'This return request has already been processed.',

    // Scheduled delivery messages
    'scheduled_delivery_single_store_only' => 'Scheduled delivery is only allowed when ordering from a single store. Please split your cart or select regular delivery.',
    'scheduled_delivery_disabled_for_store' => 'Scheduled delivery is currently disabled for this store.',
    'scheduled_delivery_date_in_past' => 'Cannot schedule delivery for a past date.',
    'scheduled_delivery_date_too_far' => 'Cannot schedule delivery more than the allowed days in advance.',
    'store_closed_on_selected_day' => 'Store is closed on the selected day.',
    'scheduled_delivery_cutoff_passed' => 'Cutoff time for scheduling delivery today has passed.',
    'scheduled_delivery_no_slots' => 'Scheduled delivery is date-only (no time slots required).',
    'scheduled_no_rush' => 'Scheduled delivery orders cannot be rush delivery.',
    'scheduled_delivery_prep_too_early' => 'Order preparation can only start at :time.',
    'scheduled_order_pending_reschedule' => 'This scheduled order is waiting for the customer to reschedule or cancel. Seller actions are paused until the customer completes recovery.',

    // Slot validation messages
    'scheduled_date_required' => 'Scheduled date is required when selecting a delivery time slot.',
    'delivery_time_slot_invalid' => 'The selected delivery time slot is invalid for the chosen date or store.',
    'delivery_time_slot_full' => 'The selected delivery time slot is fully booked. Please choose a different slot or date.',
    'delivery_time_slot_not_available' => 'The selected delivery time slot is not available for the chosen date or store.',
    'delivery_time_slot_max_orders_reached' => 'The selected delivery time slot has reached its maximum order capacity. Please choose a different slot or date.',
    'delivery_time_slot_not_active' => 'The selected delivery time slot is currently inactive. Please choose a different slot or date.',

    'allow_pickup_help' => 'Allow customers to pick up their orders at the store.',
    'pickup_cod_help' => 'Allow customers to pay with Cash on Delivery (COD) for pickup orders. If disabled, customers must use online payment methods for pickup.',

    // reasons
    'return_reason_created_successfully' => 'Return reason created successfully.',
    'return_reason_updated_successfully' => 'Return reason updated successfully.',
    'return_reason_deleted_successfully' => 'Return reason deleted successfully.',
    'rejection_reason_created_successfully' => 'Rejection reason created successfully.',
    'rejection_reason_updated_successfully' => 'Rejection reason updated successfully.',
    'rejection_reason_deleted_successfully' => 'Rejection reason deleted successfully.',
    'return_reasons_fetched_successfully' => 'Return reasons fetched successfully.',
    'return_reason_fetched_successfully' => 'Return reason fetched successfully.',
    'return_reason_not_found' => 'Return reason not found.',
    'failed_to_fetch_return_reasons' => 'Failed to fetch return reasons.',
    'updated_successfully' => 'Updated successfully.',
    'saved_successfully' => 'Saved successfully.',
    'deleted_successfully' => 'Deleted successfully.'
];
