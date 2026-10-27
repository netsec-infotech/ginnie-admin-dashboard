(function () {
    const modalElement = document.getElementById('panel-relogin-modal');

    if (!modalElement || !window.axios) {
        return;
    }

    const form = document.getElementById('panel-relogin-form');
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const panelInput = document.getElementById('panel');
    const panelName = panelInput ? panelInput.getAttribute('data-panel') : 'admin';
    const loginUrl = modalElement.getAttribute('data-login-url');
    const reloginUrl = modalElement.getAttribute('data-relogin-url');
    const checkUrl = modalElement.getAttribute('data-check-url');
    const modal = window.bootstrap ? new window.bootstrap.Modal(modalElement) : null;
    const sessionCheckThrottle = 5000;
    let lastSessionCheckAt = 0;
    let modalOpen = false;
    let refreshPromise = null;

    function setCsrfToken(token) {
        if (!token) {
            return;
        }

        if (csrfMeta) {
            csrfMeta.setAttribute('content', token);
        }

        const formToken = form?.querySelector('input[name="_token"]');
        if (formToken) {
            formToken.value = token;
        }

        window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
    }

    function fillFcmToken() {
        const tokenInput = form?.querySelector('input[name="fcm_token"]');
        if (!tokenInput) {
            return;
        }

        tokenInput.value = localStorage.getItem('fcm_token') || '';
    }

    function fireToast(options) {
        const toast = window.Toast || (typeof Toast !== 'undefined' ? Toast : null);

        if (toast) {
            toast.fire(options);
        }
    }

    function refreshReloginMetadata() {
        if (refreshPromise) {
            return refreshPromise;
        }

        refreshPromise = window.axios.get(reloginUrl, {
            headers: {'Accept': 'application/json', 'X-Panel-Relogin': '1'},
            skipPanelRelogin: true
        }).then(function (response) {
            setCsrfToken(response.data?.csrf_token);

            if (response.data?.authenticated === true && modalOpen) {
                hideReloginModal();
            }

            return response.data;
        }).finally(function () {
            refreshPromise = null;
        });

        return refreshPromise;
    }

    function showReloginModal() {
        if (modalOpen) {
            return;
        }

        modalOpen = true;
        fillFcmToken();
        refreshReloginMetadata().finally(function () {
            if (modal) {
                modal.show();
            } else if (window.jQuery) {
                window.jQuery(modalElement).modal('show');
            }
        });
    }

    function hideReloginModal() {
        modalOpen = false;
        if (modal) {
            modal.hide();
        } else if (window.jQuery) {
            window.jQuery(modalElement).modal('hide');
        }
    }

    function isPanelUrl(url) {
        try {
            const parsed = new URL(url, window.location.origin);
            return parsed.origin === window.location.origin && parsed.pathname.startsWith('/' + panelName);
        } catch (e) {
            return false;
        }
    }

    function responseLooksLikeLoginPage(response) {
        const contentType = String(response?.headers?.['content-type'] || '');
        const data = response?.data;

        return contentType.includes('text/html')
            && typeof data === 'string'
            && data.includes('id="login-form"');
    }

    function shouldOpenRelogin(error) {
        if (error?.config?.skipPanelRelogin || !isPanelUrl(error?.config?.url || '')) {
            return false;
        }

        const status = error?.response?.status;

        return status === 401 || status === 419 || responseLooksLikeLoginPage(error?.response);
    }

    window.axios.interceptors.response.use(function (response) {
        if (!response?.config?.skipPanelRelogin && isPanelUrl(response?.config?.url || '') && responseLooksLikeLoginPage(response)) {
            showReloginModal();

            return Promise.reject({
                response,
                config: response.config,
                isPanelRelogin: true
            });
        }

        return response;
    }, function (error) {
        if (shouldOpenRelogin(error)) {
            showReloginModal();
        }

        return Promise.reject(error);
    });

    form?.addEventListener('submit', function (event) {
        event.preventDefault();

        const submitButton = form.querySelector('button[type="submit"]');
        const originalButtonContent = submitButton?.innerHTML;

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.innerHTML = '<div class="spinner-border text-white me-2" role="status"><span class="visually-hidden">Loading...</span></div> ' + originalButtonContent;
        }

        refreshReloginMetadata()
            .then(function () {
                fillFcmToken();

                return window.axios.post(loginUrl, new FormData(form), {
                    headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                    skipPanelRelogin: true
                });
            })
            .then(function (response) {
                const data = response.data || {};

                if (data.success === false) {
                    fireToast({icon: 'error', title: data.message});

                    return;
                }

                setCsrfToken(data.csrf_token);
                hideReloginModal();
                form.reset();

                fireToast({icon: 'success', title: data.message});
            })
            .catch(function (error) {
                const message = error?.response?.data?.message || 'Unable to sign in. Please try again.';

                fireToast({icon: 'error', title: message});
            })
            .finally(function () {
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonContent;
                }
            });
    });

    function checkPanelSession() {
        const now = Date.now();

        if (modalOpen || document.hidden || now - lastSessionCheckAt < sessionCheckThrottle) {
            return;
        }

        lastSessionCheckAt = now;

        window.axios.get(checkUrl, {
            headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
            skipPanelRelogin: true
        }).then(function (response) {
            if (response.data?.authenticated !== true) {
                showReloginModal();
            }
        }).catch(function (error) {
            if ([401, 419].includes(error?.response?.status) || responseLooksLikeLoginPage(error?.response)) {
                showReloginModal();
            }
        });
    }

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            checkPanelSession();
        }
    });

    window.addEventListener('focus', checkPanelSession);
    document.addEventListener('pointerdown', checkPanelSession, true);
    document.addEventListener('keydown', checkPanelSession, true);
})();
