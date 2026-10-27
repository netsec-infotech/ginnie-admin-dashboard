/**
 * System Role Guided Tours Module
 */
(function() {
    'use strict';

    if (!window.TourManager) return;

    const getBasePath = () => window.location.origin;

    window.TourManager.registerTours({
        create_system_roles: {
            url: getBasePath() + '/admin/roles',
            steps: [
                {
                    element: '#roles-table_wrapper, #roles-table, .card-table',
                    popover: {
                        title: 'List of System Roles',
                        description: 'Here you can view all administrative roles and their assigned access permissions.',
                        side: 'top',
                        align: 'center'
                    }
                },
                {
                    element: '#add-role-btn, a[data-bs-target="#role-modal"]',
                    popover: {
                        title: 'Create System Role',
                        description: 'Click Add Role to define a new administrative user role.',
                        side: 'bottom',
                        align: 'center'
                    },
                    onHighlightStarted: function() {
                        const modalBtn = document.querySelector('#add-role-btn, a[data-bs-target="#role-modal"]');
                        if (modalBtn) modalBtn.click();
                    }
                },
                {
                    element: '#role-modal .modal-body',
                    popover: {
                        title: 'Role Name & Permissions Matrix',
                        description: 'Enter role name (e.g. Order Manager) and check module permissions to grant access.',
                        side: 'right',
                        align: 'start'
                    }
                },
                {
                    element: '#role-modal button[type="submit"]',
                    popover: {
                        title: 'Save Role',
                        description: 'Click Save to register the system role.',
                        side: 'top',
                        align: 'center'
                    }
                }
            ]
        }
    });
})();
