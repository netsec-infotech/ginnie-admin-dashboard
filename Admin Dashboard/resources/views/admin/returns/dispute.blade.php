@extends('layouts.admin.app', ['page' => 'returns_disputed'])

{{--
    Return Dispute System
    UI Polish
    Purpose: Segregate customer, rider, and seller evidence sections, and show interactive/read-only resolution summaries.
--}}

@section('title', 'Return Dispute Resolution #' . $return->id)

@section('header_data')
    @php
        $page_title    = 'Return Dispute Resolution #' . $return->id;
        $page_pretitle = 'Admin return moderation';
    @endphp
@endsection

@php
    use App\Enums\Order\OrderItemReturnStatusEnum;
    use App\Enums\Order\AdminLossBearerEnum;

    $breadcrumbs = [
        ['title' => __('labels.home'),            'url' => route('admin.dashboard')],
        ['title' => 'Disputed Returns',           'url' => route('admin.returns.disputed')],
        ['title' => 'Dispute #' . $return->id,     'url' => ''],
    ];

    $isResolved = !empty($return->resolved_by_admin_at);
@endphp

@section('admin-content')
    <div class="page-wrapper">
        <div class="page-body">
            <div class="container-xl">
                <!-- Return Detail Section -->
                @include('returns._detail', [
                    'return' => $return,
                    'panel' => 'admin',
                    'refundBreakdown' => $refundBreakdown,
                    'timelineSteps' => $timelineSteps,
                    'canAssignRider' => false,
                    'storeZoneId' => null,
                    'hideEvidenceCards' => true,
                ])

                <!-- Return Dispute System -->
                <!-- UI Polish -->
                <!-- Organized Evidence Deck (Customer, Rider, Seller) -->
                <div class="row mt-3">
                    <!-- Customer Evidence Card -->
                    <div class="col-12 col-lg-4 d-flex">
                        <div class="card w-100 mb-3 shadow-sm border-0">
                            <div class="card-header bg-blue-lt">
                                <h3 class="card-title text-primary"><i class="ti ti-user me-1"></i> Customer Evidence</h3>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <span class="text-secondary small d-block">Return Reason:</span>
                                    <div class="fw-medium bg-light p-2 rounded border">{{ $return->reason ?? 'No reason provided.' }}</div>
                                </div>
                                <div class="mb-3">
                                    <span class="text-secondary small d-block">Customer Description:</span>
                                    <div class="text-secondary p-2 bg-light rounded border">{{ $return->description ?? 'No customer description available.' }}</div>
                                </div>
                                <div>
                                    <span class="text-secondary small d-block mb-1">Customer Uploaded Photos:</span>
                                    @if (!empty($return->images) && count($return->images) > 0)
                                        <div class="d-flex flex-wrap gap-2">
                                            @foreach ($return->images as $imageUrl)
                                                <a href="{{ $imageUrl }}" data-fslightbox="gallery-customer" class="d-inline-block">
                                                    <img src="{{ $imageUrl }}" alt="Customer Photo"
                                                         class="rounded border img-thumbnail"
                                                         style="width: 80px; height: 80px; object-fit: cover;">
                                                </a>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="text-muted small p-2 bg-light rounded border">No customer evidence uploaded.</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Rider Information Card -->
                    <div class="col-12 col-lg-4 d-flex">
                        <div class="card w-100 mb-3 shadow-sm border-0">
                            <div class="card-header bg-green-lt">
                                <h3 class="card-title text-success"><i class="ti ti-bike me-1"></i> Rider Information</h3>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <span class="text-secondary small d-block">Assigned Rider:</span>
                                    @php
                                        $assignedRiderModel = $return->deliveryBoy;
                                    @endphp
                                    @if ($assignedRiderModel)
                                        <div class="fw-medium">{{ $assignedRiderModel->full_name ?? $assignedRiderModel->user?->name ?? '—' }}</div>
                                        <div class="text-secondary small">ID: #{{ $assignedRiderModel->id }} | Mobile: {{ $assignedRiderModel->user?->mobile ?? '—' }}</div>
                                    @else
                                        <div class="text-muted small p-2 bg-light rounded border">No rider assigned.</div>
                                    @endif
                                </div>
                                <div class="mb-3">
                                    <span class="text-secondary small d-block">Pickup Condition:</span>
                                    <div class="fw-medium">
                                        @if ($return->pickup_condition)
                                            <span class="badge bg-warning-lt">{{ ucfirst($return->pickup_condition) }}</span>
                                        @else
                                            <span class="text-muted small p-2 bg-light rounded border d-block">No rider pickup condition recorded.</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <span class="text-secondary small d-block">Pickup Remarks:</span>
                                    <div class="text-secondary p-2 bg-light rounded border">{{ $return->pickup_remarks ?? 'No rider remarks available.' }}</div>
                                </div>
                                <div>
                                    <span class="text-secondary small d-block mb-1">Pickup Evidence Photos:</span>
                                    @if (!empty($return->pickup_images) && count($return->pickup_images) > 0)
                                        <div class="d-flex flex-wrap gap-2">
                                            @foreach ($return->pickup_images as $imageUrl)
                                                <a href="{{ $imageUrl }}" data-fslightbox="gallery-rider" class="d-inline-block">
                                                    <img src="{{ $imageUrl }}" alt="Rider Pickup Photo"
                                                         class="rounded border img-thumbnail"
                                                         style="width: 80px; height: 80px; object-fit: cover;">
                                                </a>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="text-muted small p-2 bg-light rounded border">No rider evidence uploaded.</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Seller Inspection Card -->
                    <div class="col-12 col-lg-4 d-flex">
                        <div class="card w-100 mb-3 shadow-sm border-0">
                            <div class="card-header bg-warning-lt">
                                <h3 class="card-title text-warning-dark"><i class="ti ti-store me-1"></i> Seller Inspection</h3>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <span class="text-secondary small d-block">Dispute Escalation Date:</span>
                                    <div class="fw-medium">{{ $return->disputed_at ? $return->disputed_at->format('Y-m-d H:i:s') . ' (' . $return->disputed_at->diffForHumans() . ')' : '—' }}</div>
                                </div>
                                <div class="mb-3">
                                    <span class="text-secondary small d-block">Dispute Reason (Seller's Escalation):</span>
                                    <div class="fw-medium bg-light p-2 rounded border">{{ $return->dispute_reason ?? 'No dispute reason specified.' }}</div>
                                </div>
                                <div class="mb-3">
                                    <span class="text-secondary small d-block">Seller Remarks on Inspection:</span>
                                    <div class="text-secondary p-2 bg-light rounded border">{{ $return->seller_comment ?? 'No seller comments provided.' }}</div>
                                </div>
                                <div>
                                    <span class="text-secondary small d-block mb-1">Seller Inspection Photos:</span>
                                    @if (!empty($return->inspection_photos) && count($return->inspection_photos) > 0)
                                        <div class="d-flex flex-wrap gap-2">
                                            @foreach ($return->inspection_photos as $imageUrl)
                                                <a href="{{ $imageUrl }}" data-fslightbox="gallery-seller" class="d-inline-block">
                                                    <img src="{{ $imageUrl }}" alt="Seller Photo"
                                                         class="rounded border img-thumbnail"
                                                         style="width: 80px; height: 80px; object-fit: cover;">
                                                </a>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="text-muted small p-2 bg-light rounded border">No seller inspection photos uploaded.</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Admin Dispute Resolution Form / Read-Only Summary -->
                <div class="row mt-3">
                    <div class="col-12">
                        @if ($isResolved)
                            <!-- LOCKED RESOLUTION SUMMARY CARD -->
                            <div class="card border-success shadow-sm mb-3">
                                <div class="card-header bg-success text-white py-3">
                                    <h3 class="card-title text-white"><i class="ti ti-lock me-1"></i> Dispute Resolution Summary (Locked Outcome)</h3>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <span class="text-secondary small d-block">Financial Loss Bearer:</span>
                                            <div class="mt-1">
                                                @if ($return->admin_resolution === AdminLossBearerEnum::CUSTOMER)
                                                    <span class="badge bg-danger fs-3 px-3 py-2">Customer is Responsible</span>
                                                @elseif ($return->admin_resolution === AdminLossBearerEnum::SELLER)
                                                    <span class="badge bg-primary fs-3 px-3 py-2">Seller is Responsible</span>
                                                @elseif ($return->admin_resolution === AdminLossBearerEnum::RIDER)
                                                    <span class="badge bg-warning text-dark fs-3 px-3 py-2">Rider is Responsible</span>
                                                @elseif ($return->admin_resolution === AdminLossBearerEnum::PLATFORM)
                                                    <span class="badge bg-success fs-3 px-3 py-2">Platform is Responsible</span>
                                                @else
                                                    <span class="badge bg-secondary fs-3 px-3 py-2">Unknown</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <span class="text-secondary small d-block">Resolution Status:</span>
                                            <div class="mt-2 text-uppercase fw-bold text-dark fs-4">
                                                {{ str_replace('_', ' ', $return->return_status) }}
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <span class="text-secondary small d-block">Settlement Status:</span>
                                            <div class="mt-2 text-dark fs-4 fw-medium">
                                                @if ($return->admin_resolution === AdminLossBearerEnum::CUSTOMER)
                                                    <span class="text-danger fw-bold"><i class="ti ti-circle-x"></i> No Refund to Customer</span>
                                                    <span class="text-muted d-block small mt-1">Seller payout remains active.</span>
                                                @elseif ($return->admin_resolution === AdminLossBearerEnum::SELLER)
                                                    <span class="text-success fw-bold"><i class="ti ti-circle-check"></i> Customer Refunded</span>
                                                    <span class="text-muted d-block small mt-1">Debited from seller payout.</span>
                                                @elseif ($return->admin_resolution === AdminLossBearerEnum::RIDER)
                                                    <span class="text-success fw-bold"><i class="ti ti-circle-check"></i> Customer Refunded</span>
                                                    <span class="text-muted d-block small mt-1">Charged to rider (Platform Expense).</span>
                                                @elseif ($return->admin_resolution === AdminLossBearerEnum::PLATFORM)
                                                    <span class="text-success fw-bold"><i class="ti ti-circle-check"></i> Customer Refunded</span>
                                                    <span class="text-muted d-block small mt-1">Absorbed by Platform.</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <span class="text-secondary small d-block">Administrator Name:</span>
                                            <div class="mt-1 fw-medium text-dark fs-4">{{ $return->resolvedBy?->name ?? '—' }}</div>
                                        </div>
                                        <div class="col-md-4">
                                            <span class="text-secondary small d-block">Resolution Date & Time:</span>
                                            <div class="mt-1 text-dark fs-4">{{ $return->resolved_by_admin_at ? $return->resolved_by_admin_at->format('Y-m-d H:i:s') : '—' }}</div>
                                        </div>
                                        @if (in_array($return->admin_resolution, [AdminLossBearerEnum::RIDER, AdminLossBearerEnum::PLATFORM]) && $return->platformExpense)
                                            <div class="col-md-4">
                                                <span class="text-secondary small d-block">Platform Expense Reference:</span>
                                                <div class="mt-1 fw-bold text-danger fs-4">{{ $return->platformExpense->reference_number }}</div>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="mt-3 border-top pt-3">
                                        <span class="text-secondary small d-block">Administrator Remarks / Comments:</span>
                                        <div class="mt-1 p-3 bg-light rounded border text-dark fs-4 fw-medium">{{ $return->admin_comment }}</div>
                                    </div>
                                    <div class="mt-3 border-top pt-3">
                                        <span class="text-secondary small d-block mb-2">Admin Resolution Evidence Photos:</span>
                                        @if (!empty($return->resolution_photos) && count($return->resolution_photos) > 0)
                                            <div class="d-flex flex-wrap gap-2">
                                                @foreach ($return->resolution_photos as $imageUrl)
                                                    <a href="{{ $imageUrl }}" data-fslightbox="gallery-resolution" class="d-inline-block">
                                                        <img src="{{ $imageUrl }}" alt="Resolution Photo"
                                                             class="rounded border img-thumbnail"
                                                             style="width: 120px; height: 120px; object-fit: cover;">
                                                    </a>
                                                @endforeach
                                            </div>
                                        @else
                                            <div class="text-muted small">No resolution photos uploaded by the administrator.</div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @else
                            <!-- INTERACTIVE DISPUTE RESOLUTION FORM -->
                            <div class="card border-primary shadow-sm mb-3">
                                <div class="card-header bg-primary text-white">
                                    <h3 class="card-title text-white"><i class="ti ti-gavel me-1"></i> Resolve Return Dispute</h3>
                                </div>
                                <div class="card-body">
                                    <form id="resolve-dispute-form" action="{{ route('admin.returns.resolve', $return->id) }}" method="POST" enctype="multipart/form-data">
                                        @csrf
                                        @method('PUT')

                                        <!-- Who Bears the Financial Loss Selection -->
                                        <div class="mb-3">
                                            <label class="form-label required fw-medium">Who Bears the Financial Loss?</label>
                                            <div class="form-selectgroup form-selectgroup-boxes d-flex flex-column gap-2">
                                                <!-- Admin Dispute Text Fix: Updated financial loss bearer option titles and descriptions for clarity -->
                                                <label class="form-selectgroup-item flex-fill">
                                                    <input type="radio" name="action" value="customer" class="form-selectgroup-input" checked>
                                                    <span class="form-selectgroup-label d-flex align-items-center p-3">
                                                        <span class="me-3">
                                                            <span class="form-selectgroup-check"></span>
                                                        </span>
                                                        <span class="form-selectgroup-label-content">
                                                            <span class="form-selectgroup-title fw-bold text-danger">Customer is Responsible</span>
                                                            <span class="d-block text-secondary small mt-1">Return rejected • Customer gets no refund • Seller keeps the payment</span>
                                                        </span>
                                                    </span>
                                                </label>
                                                <label class="form-selectgroup-item flex-fill">
                                                    <input type="radio" name="action" value="seller" class="form-selectgroup-input">
                                                    <span class="form-selectgroup-label d-flex align-items-center p-3">
                                                        <span class="me-3">
                                                            <span class="form-selectgroup-check"></span>
                                                        </span>
                                                        <span class="form-selectgroup-label-content">
                                                            <span class="form-selectgroup-title fw-bold text-primary">Seller is Responsible</span>
                                                            <span class="d-block text-secondary small mt-1">Return approved • Customer gets a refund • Refund amount is deducted from the seller</span>
                                                        </span>
                                                    </span>
                                                </label>
                                                <label class="form-selectgroup-item flex-fill">
                                                    <input type="radio" name="action" value="rider" class="form-selectgroup-input">
                                                    <span class="form-selectgroup-label d-flex align-items-center p-3">
                                                        <span class="me-3">
                                                            <span class="form-selectgroup-check"></span>
                                                        </span>
                                                        <span class="form-selectgroup-label-content">
                                                            <span class="form-selectgroup-title fw-bold text-warning">Rider is Responsible</span>
                                                            <span class="d-block text-secondary small mt-1">Return approved • Customer gets a refund • Rider's delivery earning is deducted • Platform covers the remaining loss</span>
                                                        </span>
                                                    </span>
                                                </label>
                                                <label class="form-selectgroup-item flex-fill">
                                                    <input type="radio" name="action" value="platform" class="form-selectgroup-input">
                                                    <span class="form-selectgroup-label d-flex align-items-center p-3">
                                                        <span class="me-3">
                                                            <span class="form-selectgroup-check"></span>
                                                        </span>
                                                        <span class="form-selectgroup-label-content">
                                                            <span class="form-selectgroup-title fw-bold text-success">Platform is Responsible</span>
                                                            <span class="d-block text-secondary small mt-1">Return approved • Customer gets a refund • Seller and rider are not charged • Platform covers the full loss</span>
                                                        </span>
                                                    </span>
                                                </label>
                                            </div>
                                        </div>

                                        <!-- Remarks -->
                                        <div class="mb-3">
                                            <label class="form-label required fw-medium" for="remarks">Resolution Remarks / Decision Comments</label>
                                            <textarea class="form-control" id="remarks" name="remarks" rows="4" placeholder="Describe the reason for the decision, findings, and evidence reviewed..." required></textarea>
                                            <span class="form-text text-muted">Maximum 1000 characters. These remarks will be stored permanently.</span>
                                        </div>

                                        <!-- Resolution Photos -->
                                        <div class="mb-3">
                                            <label class="form-label fw-medium" for="resolution_photos">Upload Resolution Evidence Photos (Optional)</label>
                                            <input type="file" class="form-control" id="resolution_photos" name="resolution_photos[]" multiple accept="image/*">
                                            <span class="form-text text-muted">Supported formats: JPG, JPEG, PNG, WEBP. Maximum file size: 10MB per photo.</span>
                                        </div>

                                        <!-- Action Button -->
                                        <div class="mt-4 border-top pt-3 d-flex justify-content-end gap-2">
                                            <a href="{{ route('admin.returns.disputed') }}" class="btn btn-outline-secondary">Back to Disputed list</a>
                                            <button type="submit" class="btn btn-primary" id="btn-resolve-dispute">
                                                Resolve Dispute
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('resolve-dispute-form');
            if (form) {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();

                    const responsiblePartyRadio = form.querySelector('input[name="action"]:checked');
                    if (!responsiblePartyRadio) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Error',
                            text: 'Please select who bears the financial loss.'
                        });
                        return;
                    }

                    const remarks = form.querySelector('#remarks').value.trim();
                    if (!remarks) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Error',
                            text: 'Please enter resolution remarks.'
                        });
                        return;
                    }

                    const responsiblePartyText = responsiblePartyRadio.nextElementSibling.querySelector('.form-selectgroup-title').innerText;

                    Swal.fire({
                        title: 'Are you sure?',
                        text: `You are resolving this dispute with outcome: "${responsiblePartyText}". This decision is final and irreversible.`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Yes, Resolve Dispute',
                        cancelButtonText: 'Cancel'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            Swal.fire({
                                title: 'Resolving Dispute...',
                                text: 'Please wait while calculations are executed.',
                                allowOutsideClick: false,
                                didOpen: () => {
                                    Swal.showLoading();
                                }
                            });

                            const formData = new FormData(form);
                            
                            $.ajax({
                                url: form.getAttribute('action'),
                                method: 'POST',
                                data: formData,
                                processData: false,
                                contentType: false,
                                success: function (res) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Success',
                                        text: res.message || 'Dispute resolved successfully.',
                                        confirmButtonText: 'OK'
                                    }).then(() => {
                                        window.location.reload();
                                    });
                                },
                                error: function (xhr) {
                                    let message = 'An error occurred during resolution.';
                                    if (xhr.responseJSON && xhr.responseJSON.message) {
                                        message = xhr.responseJSON.message;
                                    }
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Dispute Resolution Failed',
                                        text: message
                                    });
                                }
                            });
                        }
                    });
                });
            }
        });
    </script>
@endpush
