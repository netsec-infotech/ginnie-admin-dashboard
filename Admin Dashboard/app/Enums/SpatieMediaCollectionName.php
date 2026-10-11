<?php

namespace App\Enums;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * @method static PROFILE_IMAGE()
 * @method static PRODUCT_MAIN_IMAGE()
 * @method static PRODUCT_ADDITIONAL_IMAGE()
 * @method static PRODUCT_VIDEO()
 * @method static BANNER_IMAGE()
 * @method static ADDRESS_PROOF()
 * @method static VOIDED_CHECK()
 * @method static VARIANT_IMAGE()
 * @method static REVIEW_IMAGES()
 * @method static DRIVER_LICENSE()
 * @method static VEHICLE_REGISTRATION()
 * @method static FEATURED_SECTION_BACKGROUND_IMAGE()
 * @method static FEATURED_SECTION_BG_DESKTOP_4K()
 * @method static FEATURED_SECTION_BG_DESKTOP_FHD()
 * @method static FEATURED_SECTION_BG_TABLET()
 * @method static FEATURED_SECTION_BG_MOBILE()
 * @method static CATEGORY_ICON()
 * @method static CATEGORY_ACTIVE_ICON()
 * @method static CATEGORY_BACKGROUND_IMAGE()
 * @method static CATEGORY_BACKGROUND_IMAGE_WEB()
 * @method static CATEGORY_BACKGROUND_IMAGE_APP()
 * @method static CATEGORY_BACKGROUND_ANIMATION()
 * @method static CATEGORY_BACKGROUND_ANIMATION_WEB()
 * @method static CATEGORY_BACKGROUND_ANIMATION_APP()
 * @method static CATEGORY_IMAGE()
 * @method static BUSINESS_LICENSE()
 * @method static ARTICLES_OF_INCORPORATION()
 * @method static NATIONAL_IDENTITY_CARD()
 * @method static AUTHORIZED_SIGNATURE()
 * @method static STORE_LOGO()
 * @method static STORE_BANNER()
 * @method static ITEM_RETURN_IMAGES()
 * @method static RETURN_PICKUP_IMAGES()
 * @method static ORDER_ITEM_ATTACHMENTS()
 * @method static SWATCHE_IMAGE()
 * @method static APP_NOTIFICATION_IMAGE()
 * @method static BRAND_LOGO()
 * @method static HOME_SECTION_BACKGROUND()
 * @method static HOME_SECTION_HERO_MEDIA()
 * @method static HOME_SECTION_CARD_BACKGROUND()
 * @method static HOME_SECTION_ITEM_IMAGE()
 * @method static SELLER_INSPECTION_PHOTOS()
 * @method static ADMIN_RESOLUTION_PHOTOS()
 */
enum SpatieMediaCollectionName: string
{
    use InvokableCases, Names, Values;

    case PRODUCT_MAIN_IMAGE = 'main_image';
    case PRODUCT_ADDITIONAL_IMAGE = 'product_additional_image';
    case PRODUCT_VIDEO = 'product_video';
    case BANNER_IMAGE = 'banner_image';
    case ADDRESS_PROOF = 'address_proof';
    case VOIDED_CHECK = 'voided_check';
    case VARIANT_IMAGE = 'variant_image';

    case REVIEW_IMAGES = 'review_images';

    case DRIVER_LICENSE = 'driver_license';
    case VEHICLE_REGISTRATION = 'vehicle_registration';
    case PROFILE_IMAGE = 'profile_image';
    case FEATURED_SECTION_BACKGROUND_IMAGE = 'featured_section_background_image';
    // Featured Section responsive backgrounds
    case FEATURED_SECTION_BG_DESKTOP_4K = 'featured_section_bg_desktop_4k';
    case FEATURED_SECTION_BG_DESKTOP_FHD = 'featured_section_bg_desktop_fhd';
    case FEATURED_SECTION_BG_TABLET = 'featured_section_bg_tablet';
    case FEATURED_SECTION_BG_MOBILE = 'featured_section_bg_mobile';
    case CATEGORY_ICON = 'category_icon';
    case CATEGORY_ACTIVE_ICON = 'category_active_icon';
    case CATEGORY_BACKGROUND_IMAGE = 'category_background_image';
    case CATEGORY_BACKGROUND_IMAGE_WEB = 'category_background_image_web';
    case CATEGORY_BACKGROUND_IMAGE_APP = 'category_background_image_app';
    case CATEGORY_BACKGROUND_ANIMATION = 'category_background_animation';
    case CATEGORY_BACKGROUND_ANIMATION_WEB = 'category_background_animation_web';
    case CATEGORY_BACKGROUND_ANIMATION_APP = 'category_background_animation_app';
    case CATEGORY_IMAGE = 'image';
    case BUSINESS_LICENSE = 'business_license';
    case ARTICLES_OF_INCORPORATION = 'articles_of_incorporation';
    case NATIONAL_IDENTITY_CARD = 'national_identity_card';
    case AUTHORIZED_SIGNATURE = 'authorized_signature';
    case STORE_LOGO = 'store_logo';
    case STORE_BANNER = 'store_banner';

    case ITEM_RETURN_IMAGES = 'item_return_images';
    case RETURN_PICKUP_IMAGES = 'return_pickup_images';
    case SELLER_REJECTION_PHOTOS = 'seller_rejection_photos';
    case SELLER_REJECTION_VIDEO = 'seller_rejection_video';

    // ==========================================================
    // Return Dispute System
    // Phase 1
    // Add seller inspection photos collection enum case
    // ==========================================================
    case SELLER_INSPECTION_PHOTOS = 'seller_inspection_photos';

    // ==========================================================
    // Return Dispute System
    // Phase 4
    // Add admin resolution photos collection enum case
    // ==========================================================
    case ADMIN_RESOLUTION_PHOTOS = 'admin_resolution_photos';

    // Order item attachments (e.g., prescriptions or required documents)
    case ORDER_ITEM_ATTACHMENTS = 'order_item_attachments';

    case SWATCHE_IMAGE = 'swatche_image';

    // App notification image
    case APP_NOTIFICATION_IMAGE = 'app_notification_image';

    // Brand logo — matches the legacy raw 'brand' collection on the Brand model.
    case BRAND_LOGO = 'brand';

    case HOME_SECTION_BACKGROUND = 'home_section_background';
    case HOME_SECTION_HERO_MEDIA = 'home_section_hero_media';
    case HOME_SECTION_CARD_BACKGROUND = 'home_section_card_background';
    case HOME_SECTION_ITEM_IMAGE = 'home_section_item_image';
}
