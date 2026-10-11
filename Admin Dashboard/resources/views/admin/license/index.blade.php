@extends('layouts.admin.app', ['page' => 'license_management'])

@section('title', 'License Management')

@section('header_data')
    @php
        $page_title = 'License Management';
        $page_pretitle = __('labels.admin') . " " . __('labels.settings');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => 'License Management', 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="page-header d-print-none">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">License Management</h2>
                <x-breadcrumb :items="$breadcrumbs" />
            </div>
        </div>
    </div>
    
    <div class="page-body">
        <div class="container-xl">
            <div class="row row-cards">
                <div class="col-12 col-md-8 mx-auto">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">License Information</h3>
                        </div>
                        <div class="card-body">
                            <p class="text-secondary mb-4">
                                This application is currently registered and bound to the domain below. 
                                If you wish to migrate your application to a new domain, you must deregister your license here first.
                            </p>
                            
                            <div class="datagrid">
                                <div class="datagrid-item">
                                    <div class="datagrid-title">Registered Domain</div>
                                    <div class="datagrid-content text-primary">{{ $domainUrl }}</div>
                                </div>
                                <div class="datagrid-item">
                                    <div class="datagrid-title">Purchase Code</div>
                                    <div class="datagrid-content">{{ $maskedCode }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent border-top-0 pt-0">
                            <div class="alert alert-warning mb-4" role="alert">
                                <h4 class="alert-title">Important: Migration Process</h4>
                                <div class="text-secondary">
                                    Deregistering your license will immediately lock this application. 
                                    Once deregistered, you can set up the application on your new domain and re-validate it there.
                                </div>
                            </div>
                            
                            <form action="{{ route('admin.license.deregister') }}" method="POST" id="deregister-form">
                                @csrf
                                <button type="button" class="btn btn-danger w-100" id="deregister-btn">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-unlink" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                       <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                       <path d="M17 22v-2"></path>
                                       <path d="M9 15l6 -6"></path>
                                       <path d="M11 6l.463 -.536a5 5 0 0 1 7.071 7.072l-.534 .464"></path>
                                       <path d="M13 18l-.397 .534a5.068 5.068 0 0 1 -7.127 0a4.972 4.972 0 0 1 0 -7.071l.524 -.463"></path>
                                       <path d="M20 17h2"></path>
                                       <path d="M2 7h2"></path>
                                       <path d="M7 2v2"></path>
                                    </svg>
                                    Deregister License
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener("DOMContentLoaded", function() {
    const deregisterBtn = document.getElementById('deregister-btn');
    const deregisterForm = document.getElementById('deregister-form');

    if (deregisterBtn) {
        deregisterBtn.addEventListener('click', function() {
            Swal.fire({
                title: 'Are you sure?',
                text: "This will unbind your license from the current domain and immediately lock this application. You must validate again on your new domain to regain access.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, deregister it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Deregistering...',
                        text: 'Please wait while we contact the license server.',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Perform AJAX POST request
                    fetch(deregisterForm.action, {
                        method: 'POST',
                        body: new FormData(deregisterForm),
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire(
                                'Deregistered!',
                                data.message,
                                'success'
                            ).then(() => {
                                window.location.href = data.data.redirect_url;
                            });
                        } else {
                            Swal.fire(
                                'Error!',
                                data.message,
                                'error'
                            );
                        }
                    })
                    .catch(error => {
                        Swal.fire(
                            'Error!',
                            'An unexpected error occurred.',
                            'error'
                        );
                    });
                }
            });
        });
    }
});
</script>
@endpush
