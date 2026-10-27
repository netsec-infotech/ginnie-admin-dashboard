@extends('vendor.installer.layouts.master')

@section('title', trans('installer_messages.welcome.title'))
@section('container')
    <p class="paragraph" style="text-align: center;">{{ trans('installer_messages.welcome.message') }}</p>
    <div class="buttons">
        <a href="{{ config('installer.branding.guide_url') }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-secondary">Installation Guide</a>
        <a href="{{ route('LaravelInstaller::requirements') }}" class="btn btn-primary">{{ trans('installer_messages.next') }}</a>
    </div>
@stop
