@php
    $removeName = 'remove_' . preg_replace('/[^A-Za-z0-9_]/', '_', str_replace('[]', '', $name));
    $isDisabled = !empty($disabled) && in_array($disabled, [true, 'true', 1, '1'], true);
    $isMultiple = !empty($multiple) && in_array($multiple, [true, 'true', 1, '1'], true);
@endphp
<div>
    <input type="hidden" name="{{ $removeName }}" value="0" data-filepond-remove-for="{{ $name }}">
    <input
        type="file"
        class="form-control"
        id="{{ $id ?? $name }}"
        name="{{ $name }}"
        data-image-url="{{ $imageUrl ?? '' }}"
        @if($isDisabled) disabled @endif
        @if($isMultiple) multiple @endif
    />
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        FilePond.registerPlugin(FilePondPluginImagePreview);
        const input = document.querySelector('[name="{{ $name }}"]');
        if (input && !input._pond) {
            let imageUrl = input.getAttribute('data-image-url');
            const removeInput = input.closest('form')?.querySelector('[data-filepond-remove-for="{{ $name }}"]');
            const pond = FilePond.create(input, {
                allowImagePreview: true,
                instantUpload: false,
                acceptedFileTypes: ['image/*'],
                credits: false,
                storeAsFile: true,
                disabled: {{ $isDisabled ? 'true' : 'false' }},
                files: imageUrl ? [{
                    source: imageUrl, options: {
                        type: 'remote'
                    }
                }] : []
            });
            input._pond = pond;
            pond.on('removefile', function () {
                if (removeInput) {
                    removeInput.value = '1';
                }
            });
            pond.on('addfile', function () {
                if (removeInput) {
                    removeInput.value = '0';
                }
            });
        }
    });
</script>
