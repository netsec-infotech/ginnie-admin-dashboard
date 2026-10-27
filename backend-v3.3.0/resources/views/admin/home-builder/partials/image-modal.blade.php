<div class="modal modal-blur fade" id="hbImageModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered hb-image-modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('labels.hb_upload') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('labels.cancel') }}"></button>
            </div>
            <div class="modal-body">
                <div id="hbImageUploadPane">
                    <input type="file" id="hbImagePond" name="hbImagePond">
                </div>
                {{-- Play Image uses this crop workspace after FilePond stages the selected image. --}}
                <div class="hb-image-crop-editor d-none" id="hbImageCropEditor">
                    <div class="hb-image-crop-head">
                        <div>
                            <strong>Crop image</strong>
                            <small>Move the image and resize the crop box.</small>
                        </div>
                        <button type="button" class="btn btn-sm btn-ghost-danger" id="hbImageCropCancel">
                            <i class="ti ti-x me-1"></i>{{ __('labels.cancel') }}
                        </button>
                    </div>
                    <div class="hb-image-crop-ratios" role="group" aria-label="Crop aspect ratio">
                        <button type="button" class="btn btn-sm btn-primary" data-crop-ratio="free">Free</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-crop-ratio="1">1:1</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-crop-ratio="1.333333">4:3</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-crop-ratio="1.5">3:2</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-crop-ratio="1.777778">16:9</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-crop-ratio="0.8">4:5</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-crop-ratio="0.666667">2:3</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-crop-ratio="3">3:1</button>
                    </div>
                    <div class="hb-image-crop-canvas">
                        <img id="hbImageCropSource" src="" alt="Crop image">
                    </div>
                    <div class="hb-image-crop-footer">
                        <div class="hb-image-crop-tools">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="hbImageCropZoomIn" title="Zoom in" aria-label="Zoom in"><i class="ti ti-zoom-in"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="hbImageCropZoomOut" title="Zoom out" aria-label="Zoom out"><i class="ti ti-zoom-out"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="hbImageCropRotateLeft" title="Rotate left" aria-label="Rotate left"><i class="ti ti-rotate-2"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="hbImageCropRotateRight" title="Rotate right" aria-label="Rotate right"><i class="ti ti-rotate-clockwise-2"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="hbImageCropReset"><i class="ti ti-refresh me-1"></i>Reset</button>
                        </div>
                        <div class="hb-image-crop-output">
                            <label class="form-label mb-0" for="hbImageCropOutputWidth">Output width</label>
                            <select class="form-select form-select-sm" id="hbImageCropOutputWidth">
                                <option value="">Original</option>
                                <option value="800">800 px</option>
                                <option value="1200">1200 px</option>
                                <option value="1600">1600 px</option>
                                <option value="2000">2000 px</option>
                            </select>
                            <button type="button" class="btn btn-sm btn-primary" id="hbImageCropApply"><i class="ti ti-crop me-1"></i>Apply crop</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" id="hbImageModalFooter">
                <button type="button" class="btn btn-ghost-secondary" data-bs-dismiss="modal">{{ __('labels.cancel') }}</button>
                <button type="button" class="btn btn-primary" id="hbImageDone">{{ __('labels.confirm') }}</button>
            </div>
        </div>
    </div>
</div>
