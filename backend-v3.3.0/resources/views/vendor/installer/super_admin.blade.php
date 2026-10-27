@extends('vendor.installer.layouts.master')

@section('title', 'Create Super Admin')
@section('style')
    <link href="{{ asset('installer/froiden-helper/helper.css') }}" rel="stylesheet"/>
    <style>
        .has-error{ color: red; }
        .has-error input{ color: black; border:1px solid red; }
    </style>
@endsection

@section('container')
    <form method="post" action="{{ route('LaravelInstaller::superAdmin.store') }}" id="super-admin-form">
        <div class="form-group">
            <label class="col-sm-2 control-label">Name</label>
            <div class="col-sm-12">
                <input type="text" name="name" class="form-control" value="{{ old('name', "") }}">
            </div>
        </div>
        <div class="form-group">
            <label class="col-sm-2 control-label">Email</label>
            <div class="col-sm-12">
                <input type="email" name="email" class="form-control" value="{{ old('email', '') }}">
            </div>
        </div>
        <div class="form-group">
            <label  class="col-sm-2 control-label">Password</label>
            <div class="col-sm-12">
                <input type="password" class="form-control" name="password" value="{{ old('password', '') }}">
            </div>
        </div>
        <div class="form-group">
            <label class="col-sm-2 control-label">Mobile</label>
            <div class="col-sm-12">
                <input type="text" name="mobile" class="form-control" value="{{ old('mobile', '') }}">
            </div>
        </div>
        <div class="modal-footer">
            <div class="buttons">
                <button class="btn btn-primary" onclick="submitForm();return false">
                    {{ trans('installer_messages.next') }}
                </button>
            </div>
        </div>
    </form>
    <script>
        function submitForm() {
            const password = document.querySelector('#super-admin-form [name="password"]').value;

            $.easyAjax({
                url: "{!! route('LaravelInstaller::superAdmin.store') !!}",
                type: "POST",
                data: $("#super-admin-form").serialize(),
                container: "#super-admin-form",
                messagePosition: "inline",
                redirect: false,
                success: function (response) {
                    if (response.status !== 'success' || response.action !== 'redirect') {
                        return;
                    }

                    try {
                        window.sessionStorage.setItem('installer_admin_password', password);
                    } catch (error) {
                    }

                    window.location.href = response.url;
                }
            });
        }
    </script>
@stop

@section('scripts')
    <script src="{{ asset('installer/js/jQuery-2.2.0.min.js') }}"></script>
    <script src="{{ asset('installer/froiden-helper/helper.js')}}"></script>
    <script>
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
    </script>
@endsection
