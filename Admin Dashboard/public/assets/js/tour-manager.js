/**
 * Guided Feature Tours Engine for Hyperlocal Admin Panel using Driver.js
 */
(function() {
    'use strict';

    if (window.TourManager) return;

    const tourStorageKey = 'active_admin_tour';
    const registeredTours = {};

    const isPathMatch = (currentPath, tour) => {
        if (!tour) return false;
        const targetPath = new URL(tour.url, window.location.origin).pathname;
        if (currentPath === targetPath) return true;
        if (tour.validPaths && Array.isArray(tour.validPaths)) {
            return tour.validPaths.some(path => currentPath.endsWith(path) || currentPath === path);
        }
        return false;
    };

    const registerTours = (toursMap) => {
        if (!toursMap) return;
        Object.assign(registeredTours, toursMap);
    };

    const startTour = (tourName) => {
        // Close offcanvas drawer if open
        const drawerEl = document.getElementById('offcanvasTourDrawer');
        if (drawerEl && window.bootstrap && window.bootstrap.Offcanvas) {
            const bsOffcanvas = window.bootstrap.Offcanvas.getInstance(drawerEl);
            if (bsOffcanvas) bsOffcanvas.hide();
        }

        const tour = registeredTours[tourName];
        if (!tour) {
            console.warn('[TourManager] Tour not registered:', tourName);
            return;
        }

        localStorage.setItem(tourStorageKey, tourName);

        const currentPath = window.location.pathname;

        if (!isPathMatch(currentPath, tour)) {
            window.location.href = tour.url;
        } else {
            runTour(tourName);
        }
    };

    const runTour = (tourName) => {
        const tour = registeredTours[tourName];
        if (!tour || !tour.steps || tour.steps.length === 0) {
            localStorage.removeItem(tourStorageKey);
            return;
        }

        let activeSteps = tour.steps;
        if (typeof tour.filterSteps === 'function') {
            activeSteps = tour.filterSteps(tour.steps, window.location.pathname);
        }

        if (!activeSteps || activeSteps.length === 0) return;

        let isDestroying = false;
        const driverObj = new window.Driver({
            animate: true,
            opacity: 0.5,
            allowClose: true,
            nextBtnText: 'Next Step →',
            prevBtnText: '← Back',
            doneBtnText: 'Finish Tour ✓',
            onDestroyStarted: () => {
                if (isDestroying) return;
                isDestroying = true;

                if (typeof tour.onTourDestroy === 'function') {
                    tour.onTourDestroy(tourName, window.location.pathname, driverObj);
                } else {
                    localStorage.removeItem('active_admin_tour');
                    localStorage.removeItem('active_seller_tour');
                }
            }
        });

        driverObj.setSteps(activeSteps);
        driverObj.start(0);
    };

    const resumeTourOnLoad = () => {
        const activeTour = localStorage.getItem('active_admin_tour') || localStorage.getItem('active_seller_tour');
        if (!activeTour) return;

        const attemptRun = (retryCount = 0) => {
            const tour = registeredTours[activeTour];
            if (!tour) {
                if (retryCount < 5) {
                    setTimeout(() => attemptRun(retryCount + 1), 200);
                } else {
                    localStorage.removeItem('active_admin_tour');
                    localStorage.removeItem('active_seller_tour');
                }
                return;
            }

            const currentPath = window.location.pathname;
            if (isPathMatch(currentPath, tour)) {
                setTimeout(() => {
                    runTour(activeTour);
                }, 450);
            }
        };

        attemptRun();
    };

    window.TourManager = {
        registerTours,
        startTour,
        runTour,
        resumeTourOnLoad,
        getTours: () => registeredTours
    };

    window.AdminTours = window.TourManager;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', resumeTourOnLoad);
    } else {
        resumeTourOnLoad();
    }
})();
