document.addEventListener('DOMContentLoaded', () => {
    $(function () {
        FilePond.registerPlugin(FilePondPluginImagePreview);
        FilePond.registerPlugin(FilePondPluginFileValidateType);
        FilePond.registerPlugin(FilePondPluginFileValidateSize);
        // Initialize FilePond for serviceAccountFile
        const serviceAccountInput = document.querySelector('[name="serviceAccountFile"]');
        const serviceAccountUrl = serviceAccountInput ? serviceAccountInput.getAttribute('data-service-url') : '';
        FilePond.create(serviceAccountInput, {
            allowImagePreview: false, // Disable image preview for non-image files
            credits: false, storeAsFile: true, acceptedFileTypes: ['application/json', 'text/plain'], // Adjust based on expected file types
            files: serviceAccountUrl ? [{
                source: serviceAccountUrl,
                options: {
                    type: 'remote'
                }
            }] : []
        });

        const systemUpdateInput = document.querySelector('[name="package"]');

        FilePond.create(systemUpdateInput, {
            allowImagePreview: false, // Disable image preview for non-image files
            credits: false,
            storeAsFile: true,
            acceptedFileTypes: [
                'application/zip',
                'application/x-zip-compressed',
                'multipart/x-zip',
            ],
            maxFileSize: '200MB',
            files: []
        })
        const imagesZip = document.querySelector('[name="images-zip"]');
        FilePond.create(imagesZip, {
            allowImagePreview: false, // Disable image preview for non-image files
            credits: false,
            storeAsFile: true,
            acceptedFileTypes: [
                'application/zip',
                'application/x-zip-compressed',
                'multipart/x-zip',
            ],
            maxFileSize: '200MB',
            files: []
        })

        function removeFieldName(inputName) {
            return 'remove_' + String(inputName).replace(/\[\]$/, '').replace(/[^A-Za-z0-9_]/g, '_');
        }

        function ensureRemoveInput(input, inputName) {
            const form = input.closest('form');
            if (!form) return null;

            const hiddenName = removeFieldName(inputName);
            let removeInput = form.querySelector(`input[type="hidden"][name="${hiddenName}"]`);
            if (!removeInput) {
                removeInput = document.createElement('input');
                removeInput.type = 'hidden';
                removeInput.name = hiddenName;
                form.appendChild(removeInput);
            }
            removeInput.value = '0';

            return removeInput;
        }

        function bindRemoveFlag(pond, removeInput) {
            if (!pond || !removeInput) return;

            pond.on('removefile', function () {
                removeInput.value = '1';
            });
            pond.on('addfile', function () {
                removeInput.value = '0';
            });
        }

        function initializeFilePond(inputName, allowFileTypes = ['image/*'], maxFileSize = null, extraOptions = {}) {
            const input = document.querySelector(`[name="${inputName}"]`);
            if (!input) return;

            const imageUrl = input.getAttribute('data-image-url') || '';
            const fileSizeLimit = maxFileSize || input.getAttribute('data-max-file-size') || null;
            const acceptedFileTypes = input.getAttribute('data-accepted-file-types')
                ? input.getAttribute('data-accepted-file-types').split(',').map(type => type.trim()).filter(Boolean)
                : allowFileTypes;
            const allowImagePreview = input.getAttribute('data-allow-image-preview');
            const removeInput = ensureRemoveInput(input, inputName);
            const pond = FilePond.create(input, Object.assign({
                allowImagePreview: allowImagePreview === null ? true : allowImagePreview !== 'false',
                credits: false,
                storeAsFile: true,
                maxFileSize: fileSizeLimit,
                acceptedFileTypes: acceptedFileTypes,
                files: imageUrl ? [{
                    source: imageUrl,
                    options: {type: 'remote'}
                }] : []
            }, extraOptions));
            input._pond = pond;
            if (input.getAttribute('data-onboarding-form-field') === 'image') {
                window.onboardingScreenImagePond = pond;
            }
            bindRemoveFlag(pond, removeInput);
        }

        // Initialize CSV FilePond for Categories Bulk Upload
        (function(){
            const csvInput = document.getElementById('csv-file');
            if (csvInput) {
                FilePond.create(csvInput, {
                    allowImagePreview: false,
                    credits: false,
                    storeAsFile: true,
                    maxFileSize: '10MB',
                    acceptedFileTypes: ['text/csv', 'application/vnd.ms-excel', '.csv'],
                    labelIdle: csvInput.getAttribute('data-label-idle') || 'Drag & Drop your CSV or <span class="filepond--label-action">Browse</span>',
                });
            }
        })();

        initializeFilePond('backgroundImage', ['image/jpeg','image/png','image/jpg','image/webp']);
        initializeFilePond('backgroundImageWeb', ['image/jpeg','image/png','image/jpg','image/webp']);
        initializeFilePond('backgroundImageWebMobile', ['image/jpeg','image/png','image/jpg','image/webp']);
        initializeFilePond('backgroundImageApp', ['image/jpeg','image/png','image/jpg','image/webp']);
        initializeFilePond('background_image', ['image/jpeg','image/png','image/jpg','image/webp']);
        initializeFilePond('background_image_web', ['image/jpeg','image/png','image/jpg','image/webp']);
        initializeFilePond('background_image_web_mobile', ['image/jpeg','image/png','image/jpg','image/webp']);
        initializeFilePond('background_image_app', ['image/jpeg','image/png','image/jpg','image/webp']);
        initializeFilePond('backgroundAnimation', ['application/json', 'text/plain', 'application/octet-stream', '.json'], '10MB', { allowImagePreview: false });
        initializeFilePond('backgroundAnimationWeb', ['application/json', 'text/plain', 'application/octet-stream', '.json'], '10MB', { allowImagePreview: false });
        initializeFilePond('backgroundAnimationApp', ['application/json', 'text/plain', 'application/octet-stream', '.json'], '10MB', { allowImagePreview: false });
        initializeFilePond('background_animation', ['application/json', 'text/plain', 'application/octet-stream', '.json'], '10MB', { allowImagePreview: false });
        initializeFilePond('background_animation_web', ['application/json', 'text/plain', 'application/octet-stream', '.json'], '10MB', { allowImagePreview: false });
        initializeFilePond('background_animation_app', ['application/json', 'text/plain', 'application/octet-stream', '.json'], '10MB', { allowImagePreview: false });
        initializeFilePond('banner');
        initializeFilePond('profile_image');
        initializeFilePond('photo', ['image/jpeg','image/png','image/webp']);
        initializeFilePond('cover', ['image/jpeg','image/png','image/webp']);
        initializeFilePond('video', ['video/mp4'], null, {allowImagePreview: false});
        initializeFilePond('address_proof');
        initializeFilePond('voided_check');
        initializeFilePond('activeIcon');
        initializeFilePond('image');
        initializeFilePond('active_icon');
        initializeFilePond('icon');
        initializeFilePond('desktop_4k_background_image');
        initializeFilePond('desktop_fdh_background_image');
        initializeFilePond('tablet_background_image');
        initializeFilePond('mobile_background_image');
        initializeFilePond('product_video', ['video/mp4', 'video/mkv', 'video/webm'], '20MB');
        initializeFilePond('siteFavicon');
        initializeFilePond('siteHeaderDarkLogo');
        initializeFilePond('siteHeaderLogo');
        initializeFilePond('siteFooterLogo');
        initializeFilePond('banner_image');
        initializeFilePond('favicon', ['image/png','image/webp']);
        initializeFilePond('logo');
        initializeFilePond('store_logo');
        initializeFilePond('store_banner');
        initializeFilePond('adminSignature')

        const input = document.querySelector(`[name="additional_images[]"]`);
        if (input) {
            const imagesJson = input.getAttribute('data-images');
            let imageUrls = [];
            if (imagesJson !== null && imagesJson !== '' && imagesJson !== undefined) {
                imageUrls = imagesJson ? JSON.parse(imagesJson) : [];
            }

            const pond = FilePond.create(input, {
                allowImagePreview: true,
                credits: false,
                storeAsFile: true,
                maxFileSize: '2MB',
                acceptedFileTypes: ['image/*'],
                files: imageUrls.map(url => ({
                    source: url,
                    options: {
                        type: 'remote'
                    }
                }))
            });
            bindRemoveFlag(pond, ensureRemoveInput(input, 'additional_images[]'));
        }
    });
});
