<label class="visually-hidden" for="translation-{{ $index }}">
    {{ __('labels.translation_for_key', ['key' => $row['key']]) }}
</label>
<input type="hidden" name="translations[{{ $index }}][key]" value="{{ $row['key'] }}">
<textarea
    id="translation-{{ $index }}"
    class="form-control"
    rows="2"
    dir="{{ $language->direction }}"
    name="translations[{{ $index }}][value]"
    data-translation-key="{{ $row['key'] }}"
    maxlength="10000"
>{{ $row['translation'] }}</textarea>

