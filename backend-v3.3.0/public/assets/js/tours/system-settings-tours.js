/**
 * System Settings Guided Tours Module
 */
(function () {
    "use strict";

    if (!window.TourManager) return;

    const getBasePath = () => window.location.origin;

    window.TourManager.registerTours({
        configure_settings: {
            url: getBasePath() + "/admin/settings/system",
            steps: [
                {
                    element: "#pills, .sticky-top",
                    popover: {
                        title: "System Settings Navigation",
                        description:
                            "Use this left menu to navigate through General, AI, Support, Cart, Wallet, and Maintenance settings.",
                        side: "right",
                    },
                },
                {
                    element: "#pills-general",
                    popover: {
                        title: "General Settings",
                        description:
                            "Configure store app name, time zone, copyright details, currency symbol, logo, and address.",
                        side: "top",
                    },
                    onHighlightStarted: function () {
                        const link = document.querySelector(
                            'a[href="#pills-general"]',
                        );
                        if (link) link.click();
                    },
                },
                {
                    element: "#pills-ai",
                    popover: {
                        title: "AI Feature Settings",
                        description:
                            "Enable AI content generation, product descriptions, and select AI model preferences.",
                        side: "top",
                    },
                    onHighlightStarted: function () {
                        const link = document.querySelector(
                            'a[href="#pills-ai"]',
                        );
                        if (link) link.click();
                    },
                },
                {
                    element: "#pills-support",
                    popover: {
                        title: "Support Information",
                        description:
                            "Set official customer support phone number, contact email, and support hours.",
                        side: "top",
                    },
                    onHighlightStarted: function () {
                        const link = document.querySelector(
                            'a[href="#pills-support"]',
                        );
                        if (link) link.click();
                    },
                },
                {
                    element: "#pills-cart",
                    popover: {
                        title: "Cart & Inventory Settings",
                        description:
                            "Configure cart expiration time, out-of-stock product visibility, and order limits.",
                        side: "top",
                    },
                    onHighlightStarted: function () {
                        const link = document.querySelector(
                            'a[href="#pills-cart"]',
                        );
                        if (link) link.click();
                    },
                },
                {
                    element: "#pills-wallet",
                    popover: {
                        title: "Wallet Settings",
                        description:
                            "Enable customer wallet payments, cashbacks, and set minimum deposit amounts.",
                        side: "top",
                    },
                    onHighlightStarted: function () {
                        const link = document.querySelector(
                            'a[href="#pills-wallet"]',
                        );
                        if (link) link.click();
                    },
                },
                {
                    element: "#pills-maintenance",
                    popover: {
                        title: "Maintenance Mode",
                        description:
                            "Toggle maintenance mode to temporarily pause store operations during system updates.",
                        side: "top",
                    },
                    onHighlightStarted: function () {
                        const link = document.querySelector(
                            'a[href="#pills-maintenance"]',
                        );
                        if (link) link.click();
                    },
                },
                {
                    element: "#pills-referral",
                    popover: {
                        title: "Referral & Earn Program",
                        description:
                            "Configure referral rewards and bonuses for existing users inviting new customers.",
                        side: "top",
                    },
                    onHighlightStarted: function () {
                        const link = document.querySelector(
                            'a[href="#pills-referral"]',
                        );
                        if (link) link.click();
                    },
                },
                {
                    element: 'form.form-submit button[type="submit"]',
                    popover: {
                        title: "Save System Settings",
                        description:
                            "Click Save Settings to apply and update all your system configurations.",
                        side: "top",
                    },
                },
            ],
        },
        configure_google_maps: {
            url: getBasePath() + "/admin/settings/web",
            validPaths: ["/admin/settings/web"],
            steps: [
                {
                    element: "#pills, .sticky-top, #pills-support",
                    popover: {
                        title: "Web Settings Navigation",
                        description:
                            "Use this menu to quickly jump to different website configuration sections including Support & Map settings.",
                        side: "right",
                    },
                },
                {
                    element: "#pills-support",
                    popover: {
                        title: "Support & Map Information",
                        description:
                            "This section contains customer contact details and the primary Google Maps API key configuration.",
                        side: "bottom",
                    },
                    onHighlightStarted: function () {
                        const link = document.querySelector(
                            'a[href="#pills-support"]',
                        );
                        if (link) link.click();
                    },
                },
                {
                    element: 'input[name="googleMapKey"]',
                    popover: {
                        title: "Google Maps API Key",
                        description:
                            "Enter your Google Maps JavaScript API key here to enable address autocomplete, interactive store maps, and geocoding.",
                        side: "bottom",
                    },
                    onHighlightStarted: function () {
                        const link = document.querySelector(
                            'a[href="#pills-support"]',
                        );
                        if (link) link.click();
                    },
                },
                {
                    element: 'textarea[name="mapIframe"]',
                    popover: {
                        title: "Map Embed iFrame",
                        description:
                            "Optionally paste a Google Maps embed iFrame HTML code to display an interactive map on your customer website contact page.",
                        side: "bottom",
                    },
                    onHighlightStarted: function () {
                        const link = document.querySelector(
                            'a[href="#pills-support"]',
                        );
                        if (link) link.click();
                    },
                },
                {
                    element: "#pills-default-location",
                    popover: {
                        title: "Set Default Map Coordinates",
                        description:
                            "Set your primary Latitude & Longitude here so maps default to your main operating area when a customer has not selected an address.",
                        side: "bottom",
                    },
                    onHighlightStarted: function () {
                        const link = document.querySelector(
                            'a[href="#pills-default-location"]',
                        );
                        if (link) link.click();
                    },
                },
                {
                    element:
                        'form.form-submit button[type="submit"], button[type="submit"]',
                    popover: {
                        title: "Save Web Settings",
                        description:
                            "Click Submit to save and activate your Google Maps API key and location settings across the platform.",
                        side: "bottom",
                    },
                },
            ],
        },
        configure_authentication_sms: {
            url: getBasePath() + "/admin/settings/authentication",
            validPaths: ["/admin/settings/authentication"],
            steps: [
                {
                    element: "#pills, .sticky-top, #pills-sms-gateway",
                    popover: {
                        title: "Authentication Settings Menu",
                        description:
                            "Navigate through SMS Gateway priority, Custom SMS, Firebase Auth, and Social Login options.",
                        side: "right",
                    },
                },
                {
                    element: "#pills-sms-gateway",
                    popover: {
                        title: "SMS Gateway Priority & Status",
                        description:
                            "Understand gateway precedence: If Custom SMS is enabled, it takes priority over Firebase. View the active gateway badge here.",
                        side: "bottom",
                    },
                    onHighlightStarted: function () {
                        const link = document.querySelector(
                            'a[href="#pills-sms-gateway"]',
                        );
                        if (link) link.click();
                    },
                },
                {
                    element:
                        'input[name="customSms"], #pills-custom-sms .form-switch',
                    popover: {
                        title: "Enable Custom SMS Gateway",
                        description:
                            "Toggle this switch ON to send OTPs using third-party SMS providers (Twilio, MessageBird, etc.).",
                        side: "bottom",
                    },
                    onHighlightStarted: function () {
                        const link = document.querySelector(
                            'a[href="#pills-custom-sms"]',
                        );
                        if (link) link.click();
                        const checkbox = document.querySelector(
                            'input[name="customSms"]',
                        );
                        const customSmsFields =
                            document.getElementById("customSmsFields");
                        if (checkbox && !checkbox.checked) {
                            checkbox.checked = true;
                            if (customSmsFields)
                                customSmsFields.style.display = "block";
                        }
                    },
                },
                {
                    element: "#customSmsFields",
                    popover: {
                        title: "Custom SMS API Configuration",
                        description:
                            "Set your SMS Gateway API URL, HTTP Method (GET/POST), Auth Headers, and Query/Body parameters. You can open the SMS Setup Guide for presets.",
                        side: "bottom",
                    },
                    onHighlightStarted: function () {
                        const link = document.querySelector(
                            'a[href="#pills-custom-sms"]',
                        );
                        if (link) link.click();
                        const customSmsFields =
                            document.getElementById("customSmsFields");
                        if (customSmsFields)
                            customSmsFields.style.display = "block";
                    },
                },
                {
                    element: "#testSmsMobile, #testSmsButton",
                    popover: {
                        title: "Test SMS Gateway",
                        description:
                            "Enter a test phone number with country code and send a test SMS to verify your configuration before saving.",
                        side: "bottom",
                    },
                    onHighlightStarted: function () {
                        const link = document.querySelector(
                            'a[href="#pills-custom-sms"]',
                        );
                        if (link) link.click();
                        const customSmsFields =
                            document.getElementById("customSmsFields");
                        if (customSmsFields)
                            customSmsFields.style.display = "block";
                    },
                },
                {
                    element:
                        'input[name="firebase"], #pills-firebase .form-switch',
                    popover: {
                        title: "Firebase Authentication",
                        description:
                            "Toggle Firebase ON to use Firebase Phone Auth & reCAPTCHA for customer phone logins.",
                        side: "bottom",
                    },
                    onHighlightStarted: function () {
                        const link = document.querySelector(
                            'a[href="#pills-firebase"]',
                        );
                        if (link) link.click();
                        const checkbox = document.querySelector(
                            'input[name="firebase"]',
                        );
                        const firebaseFields =
                            document.getElementById("firebaseFields");
                        if (checkbox && !checkbox.checked) {
                            checkbox.checked = true;
                            if (firebaseFields)
                                firebaseFields.style.display = "block";
                        }
                    },
                },
                {
                    element: "#firebaseFields",
                    popover: {
                        title: "Firebase Project Credentials",
                        description:
                            "Fill in your Firebase API Key, Auth Domain, Database URL, and Project ID from the Firebase Web Console.",
                        side: "bottom",
                    },
                    onHighlightStarted: function () {
                        const link = document.querySelector(
                            'a[href="#pills-firebase"]',
                        );
                        if (link) link.click();
                        const firebaseFields =
                            document.getElementById("firebaseFields");
                        if (firebaseFields)
                            firebaseFields.style.display = "block";
                    },
                },
                {
                    element: "#pills-social-login",
                    popover: {
                        title: "Social Logins (Google & Apple)",
                        description:
                            "Enable one-tap Google and Apple logins for web and mobile customers.",
                        side: "bottom",
                    },
                    onHighlightStarted: function () {
                        const link = document.querySelector(
                            'a[href="#pills-social-login"]',
                        );
                        if (link) link.click();
                    },
                },
                {
                    element:
                        'form.form-submit button[type="submit"], button[type="submit"]',
                    popover: {
                        title: "Save Authentication Settings",
                        description:
                            "Click Submit to save and activate your authentication and SMS gateway configurations.",
                        side: "bottom",
                    },
                },
            ],
        },
        configure_smtp_email: {
            url: getBasePath() + "/admin/settings/email",
            validPaths: ["/admin/settings/email"],
            steps: [
                {
                    element: "#pills-smtp",
                    popover: {
                        title: "SMTP Mail Configuration",
                        description:
                            "Configure your mail server details so the system can send transactional emails (order updates, password resets, and notifications).",
                        side: "bottom",
                    },
                    onHighlightStarted: function () {
                        const link = document.querySelector(
                            'a[href="#pills-smtp"]',
                        );
                        if (link) link.click();
                    },
                },
                {
                    element: 'input[name="smtpHost"]',
                    popover: {
                        title: "SMTP Host Server",
                        description:
                            "Enter your mail server host address (e.g. smtp.gmail.com, smtp.sendgrid.net, or mail.yourdomain.com).",
                        side: "bottom",
                    },
                },
                {
                    element: 'input[name="smtpPort"]',
                    popover: {
                        title: "SMTP Port",
                        description:
                            "Set the mail port used by your server (commonly 587 for TLS or 465 for SSL).",
                        side: "bottom",
                    },
                },
                {
                    element: 'input[name="smtpEmail"]',
                    popover: {
                        title: "SMTP Email / Username",
                        description:
                            "Enter the authenticating email address or username for your SMTP mail account.",
                        side: "bottom",
                    },
                },
                {
                    element: 'input[name="smtpPassword"]',
                    popover: {
                        title: "SMTP Password",
                        description:
                            "Enter your mail account password or third-party app-specific password (e.g. Google App Password).",
                        side: "bottom",
                    },
                },
                {
                    element:
                        'select[name="smtpEncryption"], select[name="smtpContentType"]',
                    popover: {
                        title: "Encryption & Content Format",
                        description:
                            "Select your encryption protocol (TLS/SSL) and email format (HTML recommended for styled emails).",
                        side: "bottom",
                    },
                },
                {
                    element: "#testEmailRecipient, #testEmailButton",
                    popover: {
                        title: "Test Live Email Delivery",
                        description:
                            "Enter your personal email address and click Send to test deliverability and verify your SMTP credentials.",
                        side: "bottom",
                    },
                },
                {
                    element:
                        'form.form-submit button[type="submit"], button[type="submit"]',
                    popover: {
                        title: "Save Email Settings",
                        description:
                            "Click Submit to save and activate your SMTP email configuration across the system.",
                        side: "bottom",
                    },
                },
            ],
        },
        configure_firebase_notifications: {
            url: getBasePath() + "/admin/settings/notification",
            validPaths: ["/admin/settings/notification"],
            steps: [
                {
                    element: 'input[name="firebaseProjectId"]',
                    popover: {
                        title: "Firebase Project ID",
                        description:
                            "Enter your unique Firebase Project ID from your Firebase Console project settings.",
                        side: "bottom",
                    },
                },
                {
                    element: 'input[name="serviceAccountFile"], pre',
                    popover: {
                        title: "Service Account JSON File",
                        description:
                            "Upload your Firebase Admin SDK service account .json file to authorize secure HTTP v1 push notifications.",
                        side: "bottom",
                    },
                },
                {
                    element: 'input[name="vapIdKey"]',
                    popover: {
                        title: "Web Push VAPID Key",
                        description:
                            "Enter your Web Push Certificate key (VAPID key) to enable in-browser customer and vendor push notifications.",
                        side: "bottom",
                    },
                },
                {
                    element: ".card-body .mt-3, .alert-success, .alert-warning",
                    popover: {
                        title: "Queue Worker & Cron Job",
                        description:
                            "Background notifications and scheduled jobs run via Laravel queue. Ensure this cron command is added to your server crontab.",
                        side: "top",
                    },
                },
                {
                    element:
                        'form.form-submit button[type="submit"], button[type="submit"]',
                    popover: {
                        title: "Save Notification Settings",
                        description:
                            "Click Submit to save and activate your Firebase push notification credentials.",
                        side: "bottom",
                    },
                },
            ],
        },
        configure_advertisement_settings: {
            url: getBasePath() + "/admin/settings/advertisement",
            validPaths: ["/admin/settings/advertisement"],
            steps: [
                {
                    element:
                        'input[name="featureEnabled"], #pills-general .form-switch',
                    popover: {
                        title: "Enable Sponsored Ads System",
                        description:
                            "Toggle this switch to activate the advertisement engine, allowing sellers to create promotional ad campaigns.",
                        side: "bottom",
                    },
                },
                {
                    element: 'select[name="disableBehavior"]',
                    popover: {
                        title: "Feature Disable Behavior",
                        description:
                            "Choose whether running ad campaigns should continue until their budget finishes or immediately pause when the ad system is disabled.",
                        side: "bottom",
                    },
                },
                {
                    element: 'input[name="cpcRate"]',
                    popover: {
                        title: "Cost Per Click (CPC) Rate",
                        description:
                            "Set the baseline fee deducted from a vendor's wallet balance for each customer click on their sponsored ad.",
                        side: "bottom",
                    },
                },
                {
                    element: 'input[name="walletMinTopup"]',
                    popover: {
                        title: "Minimum Wallet Top-up",
                        description:
                            "Specify the minimum wallet recharge amount required before a vendor can launch ad campaigns.",
                        side: "bottom",
                    },
                },
                {
                    element:
                        'input[name="impressionMultiplierMin"], input[name="impressionMultiplierMax"]',
                    popover: {
                        title: "Impression Estimator Multipliers",
                        description:
                            "Configure minimum and maximum view multipliers used to calculate estimated impressions for vendor ad budgeting.",
                        side: "top",
                    },
                },
                {
                    element:
                        'form.form-submit button[type="submit"], button[type="submit"]',
                    popover: {
                        title: "Save Advertisement Settings",
                        description:
                            "Click Submit to apply and activate your advertisement pricing and configuration.",
                        side: "bottom",
                    },
                },
            ],
        },
        configure_loyalty_points: {
            url: getBasePath() + "/admin/settings/loyalty_point",
            validPaths: [
                "/admin/settings/loyalty_point",
                "/admin/settings/loyalty-point",
            ],
            steps: [
                {
                    element:
                        'input[name="is_enabled"], #pills-general .form-switch',
                    popover: {
                        title: "Enable Loyalty Points Program",
                        description:
                            "Toggle this switch ON to allow customers to earn reward points on eligible purchases and redeem them at checkout.",
                        side: "bottom",
                    },
                },
                {
                    element: 'input[name="point_rate"]',
                    popover: {
                        title: "Point Earning Rate",
                        description:
                            "Set the spend amount required to earn 1 point. (e.g. 100 means spending 100 currency units earns 1 loyalty point).",
                        side: "bottom",
                    },
                },
                {
                    element:
                        'input[name="redemption_value"], #redemption-live-preview',
                    popover: {
                        title: "Redemption Value & Live Calculator",
                        description:
                            "Set the monetary discount per 1 redeemed point (e.g. 1.00 = 1 point gives a 1.00 currency discount) and see the live calculation demo.",
                        side: "bottom",
                    },
                },
                {
                    element: 'input[name="max_redemption_percentage"]',
                    popover: {
                        title: "Maximum Redemption Cap (%)",
                        description:
                            "Set the maximum percentage of an order total that can be paid using points (e.g. 10% caps point usage to 10% of order value).",
                        side: "bottom",
                    },
                },
                {
                    element: 'input[name="min_order_value_to_redeem"]',
                    popover: {
                        title: "Minimum Order Value to Redeem",
                        description:
                            "Specify the minimum order cart subtotal required before a customer is allowed to redeem their loyalty points.",
                        side: "bottom",
                    },
                },
                {
                    element: 'input[name="expiry_days"]',
                    popover: {
                        title: "Point Expiry (Days)",
                        description:
                            "Number of days after which earned points will automatically expire. Enter 0 for points that never expire.",
                        side: "bottom",
                    },
                },
                {
                    element:
                        'form.form-submit button[type="submit"], button[type="submit"]',
                    popover: {
                        title: "Save Loyalty Settings",
                        description:
                            "Click Submit to save and activate your loyalty points reward rules across the store.",
                        side: "bottom",
                    },
                },
            ],
        },
        configure_storage_settings: {
            url: getBasePath() + "/admin/settings/storage",
            validPaths: ["/admin/settings/storage"],
            steps: [
                {
                    element: "#pills, .sticky-top",
                    popover: {
                        title: "Storage Settings Navigation",
                        description:
                            "Use this side menu to navigate between Storage Provider, Cloud Credentials, and Media Migration & Sync.",
                        side: "right",
                    },
                },
                {
                    element: "#pills-provider",
                    popover: {
                        title: "Active Catalog Storage Provider",
                        description:
                            "Choose where e-commerce product photos, store logos, and catalog media will be stored (Local Server Storage, Amazon S3, or Cloudflare R2).",
                        side: "top",
                    },
                    onHighlightStarted: function () {
                        const link = document.querySelector(
                            'a[href="#pills-provider"]',
                        );
                        if (link) link.click();
                    },
                },
                {
                    element: "#pills-credentials",
                    popover: {
                        title: "Storage Credentials & Setup",
                        description:
                            "If Amazon S3 or Cloudflare R2 is selected, enter your access keys, secret keys, bucket name, region, and optional CDN asset URL.",
                        side: "top",
                    },
                    onHighlightStarted: function () {
                        const link = document.querySelector(
                            'a[href="#pills-credentials"]',
                        );
                        if (link) link.click();
                    },
                },
                {
                    element:
                        'form.form-submit button[type="submit"], button[type="submit"]',
                    popover: {
                        title: "Save Storage Settings",
                        description:
                            "Click Submit to apply and activate your storage driver and credentials.",
                        side: "bottom",
                    },
                },
                {
                    element: "#pills-sync",
                    popover: {
                        title: "Media Migration & Synchronization",
                        description:
                            "When switching between storage providers, use on-demand commands (via Cron Monitor) to migrate existing media files without downtime.",
                        side: "top",
                    },
                    onHighlightStarted: function () {
                        const link = document.querySelector(
                            'a[href="#pills-sync"]',
                        );
                        if (link) link.click();
                    },
                },
            ],
        },
        configure_seller_settings: {
            url: getBasePath() + "/admin/settings/seller",
            validPaths: ["/admin/settings/seller"],
            steps: [
                {
                    element: "#pills, .sticky-top",
                    popover: {
                        title: "Seller Settings Navigation",
                        description:
                            "Use this side menu to navigate between Seller Policies and Return Inspection SLA.",
                        side: "right",
                    },
                },
                {
                    element: "#pills-policies",
                    popover: {
                        title: "Seller Policies & Legal Terms",
                        description:
                            "Define Seller Terms & Conditions and Seller Privacy Policy displayed to vendor partners during onboarding and in store footers.",
                        side: "top",
                    },
                    onHighlightStarted: function () {
                        const link = document.querySelector(
                            'a[href="#pills-policies"]',
                        );
                        if (link) link.click();
                    },
                },
                {
                    element: "#pills-returns",
                    popover: {
                        title: "Seller Return Inspection SLA",
                        description:
                            "Specify the duration in hours that a seller has to inspect returned products before the return request is auto-approved in favor of the customer.",
                        side: "top",
                    },
                    onHighlightStarted: function () {
                        const link = document.querySelector(
                            'a[href="#pills-returns"]',
                        );
                        if (link) link.click();
                    },
                },
                {
                    element:
                        'form.form-submit button[type="submit"], button[type="submit"]',
                    popover: {
                        title: "Save Seller Settings",
                        description:
                            "Click Submit to save and apply your updated seller policies and SLA rules.",
                        side: "bottom",
                    },
                },
            ],
        },
        configure_delivery_boy_settings: {
            url: getBasePath() + "/admin/settings/delivery_boy",
            validPaths: [
                "/admin/settings/delivery_boy",
                "/admin/settings/delivery-boy",
            ],
            steps: [
                {
                    element: "#pills, .sticky-top",
                    popover: {
                        title: "Delivery Boy Settings Navigation",
                        description:
                            "Use this side menu to navigate between Policies, Refer & Earn, Cash Collection Limit, and Rider Tips.",
                        side: "right",
                    },
                },
                {
                    element: "#pills-policies",
                    popover: {
                        title: "Delivery Partner Policies",
                        description:
                            "Configure official Delivery Boy Terms & Conditions and Privacy Policy for delivery drivers.",
                        side: "top",
                    },
                    onHighlightStarted: function () {
                        const link = document.querySelector(
                            'a[href="#pills-policies"]',
                        );
                        if (link) link.click();
                    },
                },
                {
                    element: "#pills-refer-earn",
                    popover: {
                        title: "Driver Refer & Earn Program",
                        description:
                            "Enable referral bonuses and set monetary rewards earned by the referrer and referee drivers upon onboarding.",
                        side: "top",
                    },
                    onHighlightStarted: function () {
                        const link = document.querySelector(
                            'a[href="#pills-refer-earn"]',
                        );
                        if (link) link.click();
                    },
                },
                {
                    element: "#pills-cash-limit",
                    popover: {
                        title: "Cash Collection Limit",
                        description:
                            "Set the maximum cash-on-delivery threshold a delivery partner can hold before requiring deposit to the platform.",
                        side: "top",
                    },
                    onHighlightStarted: function () {
                        const link = document.querySelector(
                            'a[href="#pills-cash-limit"]',
                        );
                        if (link) link.click();
                    },
                },
                {
                    element: "#pills-rider-tips",
                    popover: {
                        title: "Rider Tips Configuration",
                        description:
                            "Enable customer tipping at checkout, define min/max tip amounts, preset quick tip pills, and custom tip percentages.",
                        side: "top",
                    },
                    onHighlightStarted: function () {
                        const link = document.querySelector(
                            'a[href="#pills-rider-tips"]',
                        );
                        if (link) link.click();
                    },
                },
                {
                    element:
                        'form.form-submit button[type="submit"], button[type="submit"]',
                    popover: {
                        title: "Save Delivery Boy Settings",
                        description:
                            "Click Submit to save and apply your delivery partner configuration.",
                        side: "bottom",
                    },
                },
            ],
        },
        configure_pickup_settings: {
            url: getBasePath() + "/admin/settings/pickup",
            validPaths: ["/admin/settings/pickup"],
            steps: [
                {
                    element: "#pills, .sticky-top",
                    popover: {
                        title: "Self-Pickup Navigation",
                        description:
                            "Use this side menu to navigate self-pickup settings.",
                        side: "right",
                    },
                },
                {
                    element:
                        'input[name="enabled"], #pills-general .form-switch',
                    popover: {
                        title: "Enable Store Self-Pickup Globally",
                        description:
                            "Toggle this switch ON to allow customers to choose in-store pickup at checkout, waiving delivery and drop-off fees while retaining platform commission.",
                        side: "bottom",
                    },
                },
                {
                    element:
                        'form.form-submit button[type="submit"], button[type="submit"]',
                    popover: {
                        title: "Save Self-Pickup Settings",
                        description:
                            "Click Submit to save and apply the self-pickup availability across the platform.",
                        side: "bottom",
                    },
                },
            ],
        },
        configure_scheduled_delivery_settings: {
            url: getBasePath() + "/admin/settings/scheduled_delivery",
            validPaths: [
                "/admin/settings/scheduled_delivery",
                "/admin/settings/scheduled-delivery",
            ],
            steps: [
                {
                    element: "#pills, .sticky-top",
                    popover: {
                        title: "Scheduled Delivery Navigation",
                        description:
                            "Use this side menu to navigate advance delivery scheduling options.",
                        side: "right",
                    },
                },
                {
                    element:
                        'input[name="enableScheduledDelivery"], #global-scheduled-delivery-toggle',
                    popover: {
                        title: "Enable Scheduled Delivery",
                        description:
                            "Toggle this switch ON to allow customers to schedule deliveries for future dates and specific operating time slots during checkout.",
                        side: "bottom",
                    },
                },
                {
                    element: 'input[name="maxAdvanceBookingDaysLimit"]',
                    popover: {
                        title: "Max Advance Booking Days",
                        description:
                            "Set the maximum number of days in advance a customer can schedule an order (for example, 7 days allows booking up to a week ahead).",
                        side: "bottom",
                    },
                },
                {
                    element:
                        'form.form-submit button[type="submit"], button[type="submit"]',
                    popover: {
                        title: "Save Scheduled Delivery Settings",
                        description:
                            "Click Submit to save and activate your advance scheduling rules.",
                        side: "bottom",
                    },
                },
            ],
        },
    });
})();
