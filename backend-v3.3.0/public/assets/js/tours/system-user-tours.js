/**
 * System User Guided Tours Module
 */
(function() {
    'use strict';

    if (!window.TourManager) return;

    const getBasePath = () => window.location.origin;

    window.TourManager.registerTours({
        create_system_user: {
            url: getBasePath() + '/admin/system-users',
            steps: [
                {
                    element: '#system-users-table_wrapper, #system-users-table, .card-table',
                    popover: {
                        title: 'List of System Users',
                        description: 'Here you can view all staff and administrative account users.',
                        side: 'top',
                        align: 'center'
                    }
                },
                {
                    element: '#add-system-user-btn, a[data-bs-target="#system-user-modal"]',
                    popover: {
                        title: 'Add System User',
                        description: 'Click Add System User to create a new administrative user.',
                        side: 'bottom',
                        align: 'center'
                    },
                    onHighlightStarted: function() {
                        const modalBtn = document.querySelector('#add-system-user-btn, a[data-bs-target="#system-user-modal"]');
                        if (modalBtn) modalBtn.click();
                    }
                },
                {
                    element: '#system-user-modal .modal-body',
                    popover: {
                        title: 'User Profile & Role Assignment',
                        description: 'Fill in user details, set login email, password, and assign a system role.',
                        side: 'right',
                        align: 'start'
                    }
                },
                {
                    element: '#system-user-modal button[type="submit"]',
                    popover: {
                        title: 'Save System User',
                        description: 'Click Save to register and activate the system user.',
                        side: 'top',
                        align: 'center'
                    }
                }
            ]
        }
    });
})();
