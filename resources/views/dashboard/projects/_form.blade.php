@php
    $metadataJson = old('metadata_json');

    if ($metadataJson === null && isset($project) && $project->metadata !== null) {
        $metadataJson = json_encode($project->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
@endphp

<div class="grid grid-2">
    <div class="field">
        <label for="project_name">Nama project</label>
        <input class="input" id="project_name" type="text" name="project_name" value="{{ old('project_name', $project->project_name) }}" required>
    </div>

    <div class="field">
        <label for="default_callback_url">Default callback URL</label>
        <input class="input" id="default_callback_url" type="url" name="default_callback_url" value="{{ old('default_callback_url', $project->default_callback_url) }}" placeholder="https://project.naeva.id/payment/callback">
    </div>
</div>

<input id="app_id" type="hidden" value="{{ $project->app_id }}">
<input id="secret_key" type="hidden" value="{{ $project->secret_key }}">

@if ($project->exists)
    <div class="field">
        <span class="muted">Gunakan tombol test untuk memastikan endpoint callback aktif dan bisa menerima request dari `payment.naeva.id` sebelum perubahan disimpan.</span>
    </div>
@endif

<input id="metadata_json" type="hidden" name="metadata_json" value="{{ $metadataJson }}">

<div class="field">
    <label class="checkbox-row" for="is_active">
        <input id="is_active" type="checkbox" name="is_active" value="1" @checked(old('is_active', $project->is_active ?? true))>
        <span>Project aktif dan dapat memakai API payment</span>
    </label>
</div>

<div class="button-row">
    <button class="button button-primary" type="submit">{{ $submitLabel }}</button>
    @if ($project->exists)
        <button
            class="button"
            type="button"
            data-project-callback-test-trigger
            data-project-callback-test-form="#project-callback-test-form"
        >Test Callback URL</button>
    @endif
    <a class="button" href="{{ route('dashboard.projects.index') }}">Kembali</a>
</div>
