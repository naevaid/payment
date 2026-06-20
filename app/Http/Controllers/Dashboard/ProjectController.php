<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $projects = Project::query()
            ->withCount(['transactions', 'callbackForwardingLogs'])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('project_name', 'like', "%{$search}%")
                        ->orWhere('app_id', 'like', "%{$search}%")
                        ->orWhere('default_callback_url', 'like', "%{$search}%");
                });
            })
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('dashboard.projects.index', [
            'projects' => $projects,
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('dashboard.projects.create', [
            'project' => new Project([
                'is_active' => true,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedProject($request);

        $project = Project::create([
            'app_id' => $validated['app_id'] ?: $this->generateUniqueAppId(),
            'project_name' => $validated['project_name'],
            'secret_key' => $validated['secret_key'] ?: $this->generateSecretKey(),
            'default_callback_url' => $validated['default_callback_url'],
            'is_active' => $request->boolean('is_active'),
            'metadata' => $validated['metadata'],
        ]);

        return redirect()
            ->route('dashboard.projects.show', $project)
            ->with('status', 'Project / tenant berhasil dibuat.');
    }

    public function show(Project $project): View
    {
        $project->loadCount(['transactions', 'callbackForwardingLogs']);
        $project->load([
            'transactions' => fn ($query) => $query->latest()->limit(10),
            'callbackForwardingLogs' => fn ($query) => $query->latest()->limit(10),
        ]);

        return view('dashboard.projects.show', [
            'project' => $project,
        ]);
    }

    public function edit(Project $project): View
    {
        return view('dashboard.projects.edit', [
            'project' => $project,
        ]);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $validated = $this->validatedProject($request, $project);

        $payload = [
            'app_id' => $validated['app_id'] ?: $project->app_id,
            'project_name' => $validated['project_name'],
            'default_callback_url' => $validated['default_callback_url'],
            'is_active' => $request->boolean('is_active'),
            'metadata' => $validated['metadata'],
        ];

        if ($validated['secret_key']) {
            $payload['secret_key'] = $validated['secret_key'];
        }

        $project->update($payload);

        return redirect()
            ->route('dashboard.projects.show', $project)
            ->with('status', 'Project / tenant berhasil diperbarui.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        if ($project->transactions()->exists()) {
            return back()->withErrors([
                'project' => 'Project yang sudah memiliki transaksi tidak dapat dihapus.',
            ]);
        }

        $project->delete();

        return redirect()
            ->route('dashboard.projects.index')
            ->with('status', 'Project / tenant berhasil dihapus.');
    }

    public function regenerateAppId(Project $project): RedirectResponse
    {
        $newAppId = $this->generateUniqueAppId();

        $project->update([
            'app_id' => $newAppId,
        ]);

        return redirect()
            ->route('dashboard.projects.show', $project)
            ->with('status', 'App ID project berhasil diregenerasi.')
            ->with('generated_credentials', [
                'app_id' => $newAppId,
            ]);
    }

    public function regenerateSecretKey(Project $project): RedirectResponse
    {
        $newSecretKey = $this->generateSecretKey();

        $project->update([
            'secret_key' => $newSecretKey,
        ]);

        return redirect()
            ->route('dashboard.projects.show', $project)
            ->with('status', 'Secret key project berhasil diregenerasi.')
            ->with('generated_credentials', [
                'secret_key' => $newSecretKey,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedProject(Request $request, ?Project $project = null): array
    {
        $validated = $request->validate([
            'app_id' => ['nullable', 'string', 'max:255', Rule::unique('projects', 'app_id')->ignore($project?->id)],
            'project_name' => ['required', 'string', 'max:255'],
            'secret_key' => ['nullable', 'string', 'min:16', 'max:255'],
            'default_callback_url' => ['nullable', 'url', 'max:2048'],
            'metadata_json' => ['nullable', 'string'],
        ]);

        $metadata = null;

        if (($validated['metadata_json'] ?? null) !== null && trim($validated['metadata_json']) !== '') {
            try {
                $decoded = json_decode($validated['metadata_json'], true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw ValidationException::withMessages([
                    'metadata_json' => 'Metadata harus berupa JSON yang valid.',
                ]);
            }

            if (! is_array($decoded)) {
                throw ValidationException::withMessages([
                    'metadata_json' => 'Metadata harus berupa object JSON.',
                ]);
            }

            $metadata = $decoded;
        }

        return [
            'app_id' => $validated['app_id'] ?? null,
            'project_name' => $validated['project_name'],
            'secret_key' => $validated['secret_key'] ?? null,
            'default_callback_url' => $validated['default_callback_url'] ?? null,
            'metadata' => $metadata,
        ];
    }

    protected function generateAppId(): string
    {
        return 'APP-'.Str::upper(Str::random(12));
    }

    protected function generateUniqueAppId(): string
    {
        do {
            $appId = $this->generateAppId();
        } while (Project::where('app_id', $appId)->exists());

        return $appId;
    }

    protected function generateSecretKey(): string
    {
        return Str::random(48);
    }
}
