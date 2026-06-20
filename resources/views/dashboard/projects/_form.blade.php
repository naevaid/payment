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
        <label for="app_id">App ID</label>
        <input class="input" id="app_id" type="text" name="app_id" value="{{ old('app_id', $project->app_id) }}" placeholder="Kosongkan untuk generate otomatis">
    </div>
</div>

<div class="grid grid-2">
    <div class="field">
        <label for="secret_key">Secret key</label>
        <input class="input" id="secret_key" type="text" name="secret_key" value="" placeholder="{{ $project->exists ? 'Kosongkan jika tidak ingin mengganti secret key' : 'Kosongkan untuk generate otomatis' }}">
    </div>

    <div class="field">
        <label for="default_callback_url">Default callback URL</label>
        <input class="input" id="default_callback_url" type="url" name="default_callback_url" value="{{ old('default_callback_url', $project->default_callback_url) }}" placeholder="https://client.naeva.id/payment/callback">
    </div>
</div>

<div class="field">
    <label for="metadata_json">Metadata JSON</label>
    <textarea class="textarea" id="metadata_json" name="metadata_json" placeholder='{"team":"finance","channel":"internal"}'>{{ $metadataJson }}</textarea>
</div>

<div class="field">
    <label class="checkbox-row" for="is_active">
        <input id="is_active" type="checkbox" name="is_active" value="1" @checked(old('is_active', $project->is_active ?? true))>
        <span>Project aktif dan dapat memakai API payment</span>
    </label>
</div>

<div class="button-row">
    <button class="button button-primary" type="submit">{{ $submitLabel }}</button>
    <a class="button" href="{{ route('dashboard.projects.index') }}">Kembali</a>
</div>
