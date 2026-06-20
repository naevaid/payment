<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

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

    public function testCallback(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'app_id' => ['nullable', 'string', 'max:255'],
            'secret_key' => ['nullable', 'string', 'max:255'],
            'callback_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $callbackUrl = $validated['callback_url'] ?? $project->default_callback_url;

        if (blank($callbackUrl)) {
            return back()
                ->withInput()
                ->withErrors([
                    'default_callback_url' => 'Isi callback URL terlebih dahulu sebelum menjalankan test.',
                ]);
        }

        $appId = filled($validated['app_id'] ?? null) ? $validated['app_id'] : $project->app_id;
        $secretKey = filled($validated['secret_key'] ?? null) ? $validated['secret_key'] : $project->secret_key;
        $testedAt = now();
        $payload = $this->buildCallbackTestPayload($project, $appId, $callbackUrl, $testedAt);
        $headers = $this->buildCallbackTestHeaders($appId, $secretKey, $payload, $testedAt);

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout((int) config('payment.callback.timeout_seconds', 10))
                ->withHeaders($headers)
                ->post($callbackUrl, $payload);

            $result = [
                'success' => $response->successful(),
                'app_id' => $appId,
                'callback_url' => $callbackUrl,
                'status_code' => $response->status(),
                'response_body' => Str::limit($response->body(), 1000),
                'tested_at' => $testedAt->format('d M Y H:i:s'),
                'delivery_id' => $headers['X-Payment-Delivery-Id'],
                'event_type' => $headers['X-Payment-Event'],
            ];

            $redirect = back()
                ->withInput()
                ->with('callback_test', $result);

            if ($response->successful()) {
                return $redirect->with(
                    'status',
                    'Test callback berhasil. Endpoint membalas HTTP '.$response->status().'.'
                );
            }

            return $redirect->withErrors([
                'default_callback_url' => 'Test callback gagal. Endpoint membalas HTTP '.$response->status().'.',
            ]);
        } catch (Throwable $exception) {
            return back()
                ->withInput()
                ->with('callback_test', [
                    'success' => false,
                    'app_id' => $appId,
                    'callback_url' => $callbackUrl,
                    'status_code' => null,
                    'response_body' => null,
                    'tested_at' => $testedAt->format('d M Y H:i:s'),
                    'delivery_id' => $headers['X-Payment-Delivery-Id'],
                    'event_type' => $headers['X-Payment-Event'],
                    'error_message' => $exception->getMessage(),
                ])
                ->withErrors([
                    'default_callback_url' => 'Test callback gagal dikirim: '.$exception->getMessage(),
                ]);
        }
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

    /**
     * @return array<string, mixed>
     */
    protected function buildCallbackTestPayload(
        Project $project,
        string $appId,
        string $callbackUrl,
        \Illuminate\Support\Carbon $testedAt,
    ): array {
        return [
            'test' => true,
            'event' => 'payment.callback.test',
            'message' => 'This is a callback connectivity test from payment.naeva.id',
            'app_id' => $appId,
            'project_name' => $project->project_name,
            'callback_url' => $callbackUrl,
            'sent_at' => $testedAt->toDateTimeString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    protected function buildCallbackTestHeaders(
        string $appId,
        string $secretKey,
        array $payload,
        \Illuminate\Support\Carbon $testedAt,
    ): array {
        return [
            'User-Agent' => (string) config('payment.callback.user_agent'),
            'X-Payment-App-Id' => $appId,
            'X-Payment-Event' => 'payment.callback.test',
            'X-Payment-Attempt' => '1',
            'X-Payment-Timestamp' => (string) $testedAt->timestamp,
            'X-Payment-Delivery-Id' => (string) Str::uuid(),
            'X-Payment-Signature' => hash_hmac(
                'sha256',
                json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '',
                $secretKey,
            ),
        ];
    }
}
