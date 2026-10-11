/**
 * Seller Management Guided Tours Module
 */
(function () {
    "use strict";

    if (!window.TourManager) return;

    const getBasePath = () => window.location.origin;

    window.TourManager.registerTours({
        create_seller: {
            url: getBasePath() + "/admin/sellers",
            validPaths: ["/admin/sellers", "/admin/sellers/create"],
            filterSteps: function (steps, currentPath) {
                const isFormPage = currentPath.endsWith(
                    "/admin/sellers/create",
                );
                if (isFormPage) {
                    return steps.filter(
                        (step) =>
                            !step.element.includes("sellers-table") &&
                            !step.element.includes("add-seller-btn"),
                    );
                } else {
                    return steps.filter(
                        (step) =>
                            step.element.includes("sellers-table") ||
                            step.element.includes("add-seller-btn"),
                    );
                }
            },
            onTourDestroy: function (tourName, currentPath) {
                if (currentPath.endsWith("/admin/sellers")) {
                    localStorage.setItem("active_admin_tour", tourName);
                    var addLink = document.querySelector(
                        '#add-seller-btn, .card-actions a[href*="/sellers/create"]',
                    );
                    window.location.href = addLink
                        ? addLink.href
                        : getBasePath() + "/admin/sellers/create";
                } else {
                    localStorage.removeItem("active_admin_tour");
                }
            },
            steps: [
                {
                    element:
                        "#sellers-table_wrapper, #sellers-table, .card-table",
                    popover: {
                        title: "List of Sellers",
                        description:
                            "Here you can see the list of all sellers registered in the system.",
                        side: "top",
                        align: "center",
                    },
                },
                {
                    element:
                        '#add-seller-btn, .card-actions a[href*="/sellers/create"]',
                    popover: {
                        title: "Add Seller",
                        description:
                            "Click on the Add Seller button to open the registration form.",
                        side: "bottom",
                        align: "center",
                        doneBtnText: "Go to Form →",
                    },
                },
                {
                    element: "#pills-basic",
                    popover: {
                        title: "Basic Details",
                        description:
                            "Enter the seller name, country, mobile number, email address, and login password.",
                        side: "bottom",
                        align: "start",
                    },
                },
                {
                    element: "#pills-location",
                    popover: {
                        title: "Location Details",
                        description:
                            "Enter the address, city, landmark, state, and zipcode of the seller store.",
                        side: "bottom",
                        align: "start",
                    },
                },
                {
                    element: "#pills-documents",
                    popover: {
                        title: "Business Documents",
                        description:
                            "Upload required business license, articles of incorporation, national ID, and authorized signature.",
                        side: "bottom",
                        align: "start",
                    },
                },
                {
                    element: "#pills-status",
                    popover: {
                        title: "Status & Metadata",
                        description:
                            "Configure verification status (approved/not approved), metadata, and visibility status.",
                        side: "bottom",
                        align: "start",
                    },
                },
                {
                    element: 'form.form-submit button[type="submit"]',
                    popover: {
                        title: "Register Seller",
                        description:
                            "Click this button to save and register the seller into the system.",
                        side: "top",
                        align: "center",
                    },
                },
            ],
        },
        approve_reject_store: {
            url: getBasePath() + "/admin/sellers/store",
            validPaths: ["/admin/sellers/store"],
            steps: [
                {
                    element:
                        ".row-cards:has(#insight-total-stores), .row.row-cards:first-of-type, #insight-total-stores",
                    popover: {
                        title: "Store Application Overview",
                        description:
                            "Monitor real-time counters for Total Listed Stores, Approved Stores, Recommended Stores, and pending Not Approved applications.",
                        side: "bottom",
                        align: "center",
                    },
                },
                {
                    element: "#verification-status",
                    popover: {
                        title: "Filter by Verification Status",
                        description:
                            "Filter the table by Approved or Not Approved status to quickly identify pending store submissions requiring admin review.",
                        side: "bottom",
                        align: "start",
                    },
                },
                {
                    element: "#recommended-status, #visibility-status",
                    popover: {
                        title: "Visibility & Recommendation Filters",
                        description:
                            "Filter stores by customer visibility (Visible vs. Draft) and whether the store is currently featured as Recommended.",
                        side: "bottom",
                        align: "start",
                    },
                },
                {
                    element:
                        "#stores-table_wrapper, #stores-table, .card-table",
                    popover: {
                        title: "Stores Management Table",
                        description:
                            "Browse all seller stores. Stores awaiting review show an animated red dot next to their ID.",
                        side: "top",
                        align: "center",
                    },
                },
                {
                    element:
                        "#stores-table_wrapper, #stores-table, .card-table",
                    popover: {
                        title: "How to Approve or Reject a Store",
                        description:
                            "1. Click the Eye (View) icon on any store in the table.<br>2. Inspect location, operating hours, bank details, and address proof documents.<br>3. Under Verify Account, set Verification Status to Approved or Rejected and Visibility to Visible.<br>4. Click Submit to activate the store live on the platform.",
                        side: "top",
                        align: "center",
                    },
                },
            ],
        },
    });
})();
