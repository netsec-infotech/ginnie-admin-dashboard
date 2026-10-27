(function () {
    const page = document.querySelector(".currency-management-page");

    if (!page || !window.DatatableUtils) {
        return;
    }

    const initializeTables = (root) => {
        root.querySelectorAll("[data-lazy-datatable='true']").forEach((table) => {
            window.DatatableUtils.initializeDatatable(table);
            table.dataset.lazyDatatableLoaded = "true";
        });
    };

    const activePane = page.querySelector(".tab-pane.active.show");
    if (activePane) {
        initializeTables(activePane);
    }

    page.querySelectorAll('[data-bs-toggle="tab"]').forEach((tab) => {
        tab.addEventListener("shown.bs.tab", (event) => {
            const targetSelector = event.target.getAttribute("href");
            const pane = targetSelector ? page.querySelector(targetSelector) : null;

            if (pane) {
                initializeTables(pane);
            }
        });
    });
})();
