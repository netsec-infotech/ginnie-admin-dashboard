<div class="mb-3">
    <label class="form-label required">{{ __('labels.watch_buy_username') }}</label>
    <div class="input-group">
        <span class="input-group-text">@</span>
        <input class="form-control" name="username" value="{{ old('username', $profile?->username) }}"
            minlength="3" maxlength="30" pattern="[a-z0-9._]+" required @disabled($disabled ?? false)>
    </div>
    <small class="form-hint">{{ __('labels.watch_buy_username_hint') }}</small>
    @error('username')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
<div class="mb-3">
    <label class="form-label required">{{ __('labels.watch_buy_bio') }}</label>
    <textarea class="form-control" name="bio" maxlength="160" rows="4" required @disabled($disabled ?? false)>{{ old('bio', $profile?->bio) }}</textarea>
    @error('bio')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
<div class="mb-3">
    <label class="form-label {{ $profile ? '' : 'required' }}">{{ __('labels.watch_buy_profile_photo') }}</label>
    <input class="form-control" type="file" name="photo" accept="image/jpeg,image/png,image/webp"
        data-image-url="{{ $profile?->photo_url ?? '' }}" data-max-file-size="5MB" @required(!$profile) @disabled($disabled ?? false)>
    @error('photo')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
