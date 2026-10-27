document.addEventListener('show.bs.modal', function (event) {
    if (event.target.id === 'category-modal') {
        const triggerButton = event.relatedTarget;
        const categoryId = triggerButton.getAttribute('data-id');
        let url = base_url + '/admin/categories/' + categoryId + '/edit';

        const form = document.querySelector('.form-submit');
        const imageUpload = document.querySelector('#image-upload');
        const bannerUpload = document.querySelector('#banner-upload');
        const iconUpload = document.querySelector('#icon-upload');
        const activeIconUpload = document.querySelector('#active-icon-upload');
        const backgroundImageUpload = document.querySelector('#background-image-upload');
        const backgroundImageWebUpload = document.querySelector('#background-image-web-upload');
        const backgroundImageAppUpload = document.querySelector('#background-image-app-upload');
        const backgroundAnimationUpload = document.querySelector('#background-animation-upload');
        const backgroundAnimationWebUpload = document.querySelector('#background-animation-web-upload');
        const backgroundAnimationAppUpload = document.querySelector('#background-animation-app-upload');
        const backgroundTypeSelect = document.querySelector('#background-type-select');
        const modalTitle = document.querySelector('#category-modal .modal-title');
        const submitButton = document.querySelector('#category-modal button[type="submit"]');
        const parentSelect = document.getElementById("select-parent-category");
        const searchLabelsSelect = document.getElementById("category-search-labels");
        const tomSelectInstance = parentSelect && parentSelect.tomselect; // TomSelect instance
// Remove files from FilePond if available
        if (typeof FilePond !== 'undefined') {
            const pond = FilePond.find(imageUpload);
            if (pond) pond.removeFiles();
            const bannerPond = FilePond.find(bannerUpload);
            if (bannerPond) bannerPond.removeFiles();
            const iconPond = FilePond.find(iconUpload);
            if (iconPond) iconPond.removeFiles();
            const activeIconPond = FilePond.find(activeIconUpload);
            if (activeIconPond) activeIconPond.removeFiles();
            const backgroundImagePond = FilePond.find(backgroundImageUpload);
            if (backgroundImagePond) backgroundImagePond.removeFiles();
            const backgroundImageWebPond = FilePond.find(backgroundImageWebUpload);
            if (backgroundImageWebPond) backgroundImageWebPond.removeFiles();
            const backgroundImageAppPond = FilePond.find(backgroundImageAppUpload);
            if (backgroundImageAppPond) backgroundImageAppPond.removeFiles();
            const backgroundAnimationPond = FilePond.find(backgroundAnimationUpload);
            if (backgroundAnimationPond) backgroundAnimationPond.removeFiles();
            const backgroundAnimationWebPond = FilePond.find(backgroundAnimationWebUpload);
            if (backgroundAnimationWebPond) backgroundAnimationWebPond.removeFiles();
            const backgroundAnimationAppPond = FilePond.find(backgroundAnimationAppUpload);
            if (backgroundAnimationAppPond) backgroundAnimationAppPond.removeFiles();
        }
        if (categoryId) {
            // Fetch category data
            fetch(url, {method: 'GET'})
                .then(response => response.json())
                .then(async responseData => {
                    const data = responseData.data;
                    // Fill form fields
                    form.querySelector('input[name="title"]').value = data.title || '';
                    form.querySelector('input[id="category-id"]').value = categoryId;
                    form.querySelector('textarea[name="description"]').value = data.description || '';
                    form.querySelector('input[name="status"]').checked = data.status === 'active';
                    form.querySelector('input[name="requires_approval"]').checked = !!data.requires_approval;
                    form.querySelector('input[name="commission"]').value = data.commission || 0;

                    // Set background fields
                    if (backgroundTypeSelect) {
                        backgroundTypeSelect.value = data.background_type || '';
                        toggleBackgroundFields(data.background_type);
                    }
                    if (data.background_color) {
                        form.querySelector('input[name="background_color"]').value = data.background_color;
                    }
                    if (data.font_color) {
                        form.querySelector('input[name="font_color"]').value = data.font_color;
                    } else {

                        form.querySelector('input[name="font_color"]').value = '#000000';
                    }

                    // Set parent_id in TomSelect (auto-select)
                    if (tomSelectInstance) {
                        // If the parent is not in the options yet, load it
                        if (data.parent) {
                            let parentOption = tomSelectInstance.options[data.parent.id];
                            if (!parentOption) {
                                // Fetch the parent (if not already loaded)
                                await fetch(base_url + '/admin/categories/search' + `?q=${data.parent.title}`)
                                    .then(res => res.json())
                                    .then(json => {
                                        if (json && json.length) {
                                            tomSelectInstance.addOption(json[0]);
                                        }
                                    });
                            }
                            tomSelectInstance.setValue(data.parent_id);
                        } else {
                            tomSelectInstance.clear();
                        }
                    }

                    if (searchLabelsTomSelect) {
                        searchLabelsTomSelect.clear();
                        searchLabelsTomSelect.clearOptions();
                        (data.search_labels || []).forEach((label) => {
                            searchLabelsTomSelect.addOption({value: label, text: label});
                            searchLabelsTomSelect.addItem(label);
                        });
                    } else if (searchLabelsSelect) {
                        searchLabelsSelect.innerHTML = '';
                        (data.search_labels || []).forEach((label) => {
                            const option = document.createElement('option');
                            option.value = label;
                            option.text = label;
                            option.selected = true;
                            searchLabelsSelect.appendChild(option);
                        });
                    }

                    // Image upload via FilePond
                    if (typeof FilePond !== 'undefined') {
                        if (data.image !== null && data.image !== undefined && imageUpload && data.image !== '') {
                            const pond = FilePond.find(imageUpload);
                            if (pond) {
                                pond.addFile(data.image);
                            }
                        }
                        if (data.banner !== null && data.banner !== undefined && bannerUpload && data.banner !== '') {
                            const bannerPond = FilePond.find(bannerUpload);
                            if (bannerPond) {
                                bannerPond.addFile(data.banner);
                            }
                        }
                        if (data.icon !== null && data.icon !== undefined && iconUpload && data.icon !== '') {
                            const iconPond = FilePond.find(iconUpload);
                            if (iconPond) {
                                iconPond.addFile(data.icon);
                            }
                        }
                        if (data.active_icon !== null && data.active_icon !== undefined && activeIconUpload && data.active_icon !== '') {
                            const activeIconPond = FilePond.find(activeIconUpload);
                            if (activeIconPond) {
                                activeIconPond.addFile(data.active_icon);
                            }
                        }
                        if (data.background_image !== null && data.background_image !== undefined && backgroundImageUpload && data.background_image !== '') {
                            const backgroundImagePond = FilePond.find(backgroundImageUpload);
                            if (backgroundImagePond) {
                                backgroundImagePond.addFile(data.background_image);
                            }
                        }
                        const bgImgWeb = data.background_image_web || data.background_image;
                        if (bgImgWeb && backgroundImageWebUpload) {
                            const pond = FilePond.find(backgroundImageWebUpload);
                            if (pond) pond.addFile(bgImgWeb);
                        }
                        const bgImgApp = data.background_image_app || data.background_image;
                        if (bgImgApp && backgroundImageAppUpload) {
                            const pond = FilePond.find(backgroundImageAppUpload);
                            if (pond) pond.addFile(bgImgApp);
                        }

                        if (data.background_animation !== null && data.background_animation !== undefined && backgroundAnimationUpload && data.background_animation !== '') {
                            const backgroundAnimationPond = FilePond.find(backgroundAnimationUpload);
                            if (backgroundAnimationPond) {
                                backgroundAnimationPond.addFile(data.background_animation);
                            }
                        }
                        const bgAnimWeb = data.background_animation_web || data.background_animation;
                        if (bgAnimWeb && backgroundAnimationWebUpload) {
                            const pond = FilePond.find(backgroundAnimationWebUpload);
                            if (pond) pond.addFile(bgAnimWeb);
                        }
                        const bgAnimApp = data.background_animation_app || data.background_animation;
                        if (bgAnimApp && backgroundAnimationAppUpload) {
                            const pond = FilePond.find(backgroundAnimationAppUpload);
                            if (pond) pond.addFile(bgAnimApp);
                        }
                    }

                    // Change form action to update route
                    form.setAttribute('action', base_url + `/admin/categories/${categoryId}`);

                    // Update modal title and button
                    modalTitle.textContent = 'Edit Category';
                    submitButton.textContent = 'Update Category';
                })
                .catch(error => {
                    console.error('AJAX Error:', error);
                });
        } else {
            // New category mode
            if (form) form.reset();
            // Remove _method input if it exists
            const methodInput = form.querySelector('input[name="_method"]');
            if (methodInput) methodInput.parentNode.removeChild(methodInput);

            // Reset TomSelect
            if (tomSelectInstance) tomSelectInstance.clear();
            if (searchLabelsTomSelect) {
                searchLabelsTomSelect.clear();
                searchLabelsTomSelect.clearOptions();
            } else if (searchLabelsSelect) {
                searchLabelsSelect.innerHTML = '';
            }

            // Reset background fields
            if (backgroundTypeSelect) {
                backgroundTypeSelect.value = '';
                toggleBackgroundFields('');
            }
            form.querySelector('input[name="background_color"]').value = '';

            // Set action for create
            form.querySelector('input[id="category-id"]').value = "";
            form.setAttribute('action', base_url + '/admin/categories');
            modalTitle.textContent = 'Create Category';
            submitButton.textContent = 'Create new Category';
        }
    }
    if (event.target.id === 'faq-modal') {
        const triggerButton = event.relatedTarget;
        const conditionId = triggerButton ? triggerButton.getAttribute('data-id') : null;
        let url = `${base_url}/${panel}/faqs/${conditionId}/edit`;

        const form = document.querySelector('#faq-modal .form-submit');
        const modalTitle = document.querySelector('#faq-modal .modal-title');
        const submitButton = document.querySelector('#faq-modal button[type="submit"]');

        if (conditionId) {
            // Edit mode: Fetch and populate data
            fetch(url, {method: 'GET'})
                .then(response => response.json())
                .then(async responseData => {
                    const data = responseData.data;

                    // Fill form fields
                    form.querySelector('textarea[name="question"]').value = data.question || '';
                    form.querySelector('textarea[name="answer"]').value = data.answer || '';
                    form.querySelector('select[name="status"]').value = data.status || '';

                    // Change form action to update route
                    form.setAttribute('action', `${base_url}/${panel}/faqs/${conditionId}`);

                    // Update modal title and button
                    modalTitle.textContent = 'Edit Faq';
                    submitButton.innerHTML = '<i class="ti ti-edit me-1"></i> Update';
                })
                .catch(error => {
                    console.error('AJAX Error:', error);
                });
        } else {
            // New condition mode: Reset fields
            if (form) form.reset();
            form.querySelector('textarea[name="question"]').value = '';
            form.querySelector('textarea[name="answer"]').value = '';
            form.querySelector('select[name="status"]').value = 'active';
            // Set action for create
            form.setAttribute('action', `${base_url}/${panel}/faqs`);
            modalTitle.textContent = 'Add Faq';
            submitButton.innerHTML = '<i class="ti ti-plus me-1"></i> Add';
        }
    }
});

// Delete category
document.addEventListener('click', function (event) {
    handleDelete(event, '.delete-category', `/${panel}/categories/`, 'You are about to delete this Category.');
    handleDelete(event, '.delete-faq', `/${panel}/faqs/`, 'You are about to delete this Faq.');
});

// Background type toggle function
function toggleBackgroundFields(backgroundType) {
    const backgroundColorField = document.getElementById('background-color-field');
    const backgroundImageField = document.getElementById('background-image-field');
    const backgroundAnimationField = document.getElementById('background-animation-field');

    if (backgroundColorField) backgroundColorField.style.display = (backgroundType === 'color') ? 'block' : 'none';
    if (backgroundImageField) backgroundImageField.style.display = (backgroundType === 'image') ? 'block' : 'none';
    if (backgroundAnimationField) backgroundAnimationField.style.display = (backgroundType === 'animation') ? 'block' : 'none';
}

// Background type select event listener
document.addEventListener('change', function (event) {
    if (event.target.id === 'background-type-select') {
        toggleBackgroundFields(event.target.value);
    }
});

let tomSelectInstance;
let searchLabelsTomSelect;

try {
    tomSelectInstance = new TomSelect('.search-labels', {
        create: true,
        maxItems: 3
    });
} catch (e) {
}
try {
    searchLabelsTomSelect = new TomSelect('#category-search-labels', {
        create: true,
        persist: false,
        copyClassesToDropdown: false,
        controlInput: "<input>",
        valueField: "value",
        labelField: "text",
        searchField: "text",
        load: function (query, callback) {
            if (!query.length) return callback();
            fetch(base_url + '/admin/categories/search-labels?q=' + encodeURIComponent(query))
                .then(response => response.json())
                .then(json => {
                    callback(json);
                })
                .catch(() => {
                    callback();
                });
        }
    });
} catch (e) {
}
document.querySelector('.generate-search-labels-button')?.addEventListener('click', function () {
    if (!tomSelectInstance) return;

    // Pool of random keywords
    const keywords = [
        'Grocery', 'Electronics', 'Daily Essentials', 'Fashion',
        'Beauty', 'Toys', 'Stationery', 'Books', 'Sports', 'Furniture'
    ];

    // Shuffle and pick 3 random keywords
    const randomKeywords = keywords.sort(() => 0.5 - Math.random()).slice(0, 3);

    // Clear old selections
    tomSelectInstance.clear();

    // Add "Search for ..." items
    randomKeywords.forEach(keyword => {
        const label = `Search for ${keyword}`;
        const value = label.toLowerCase().replace(/\s+/g, '_');
        tomSelectInstance.addOption({value, text: label});
        tomSelectInstance.addItem(value);
    });
});
// Authentication-settings page handlers have moved to
// public/assets/js/authentication-settings.js (per §6.1 module-scoped JS).
// Do not re-add addField / customSms toggle / SMS gateway badge logic here —
// duplicating it caused every "Add Header/Param/Body" click to insert two rows.

document.addEventListener('DOMContentLoaded', function () {
    /* ====================================================================
       Sidebar Scroll Position Retention Across Page Loads
       ==================================================================== */
    const sidebarMenu = document.getElementById('sidebar-menu');
    if (sidebarMenu) {
        const storageKey = 'admin_sidebar_scroll_top';
        const savedScrollTop = sessionStorage.getItem(storageKey);

        if (savedScrollTop !== null) {
            sidebarMenu.scrollTo({
                top: parseInt(savedScrollTop, 10),
                behavior: 'smooth'
            });
        } else {
            // If no saved scroll position, ensure the active menu item is scrolled into view smoothly
            const activeItem = sidebarMenu.querySelector('.nav-item.active, .dropdown-item.active');
            if (activeItem) {
                activeItem.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
            }
        }

        // Save scroll position on scroll
        let scrollTimeout;
        sidebarMenu.addEventListener('scroll', function () {
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(function () {
                sessionStorage.setItem(storageKey, sidebarMenu.scrollTop);
            }, 100);
        }, { passive: true });

        // Save position immediately when clicking any sidebar link
        sidebarMenu.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                sessionStorage.setItem(storageKey, sidebarMenu.scrollTop);
            });
        });
    }
});
