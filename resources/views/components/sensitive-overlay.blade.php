{{-- Warning shown over a blurred sensitive image until the visitor chooses to see it (see resources/js/app.js). --}}
<div class="sensitive-overlay">
    <x-icons.eye-off class="size-8" />
    <strong>{{ __('site.media.sensitive_label') }}</strong>
    <span>{{ __('site.media.sensitive_text') }}</span>
    <button type="button" data-sensitive-reveal>{{ __('site.media.sensitive_show') }}</button>
</div>
