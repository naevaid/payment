<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChargeRequest;
use App\Models\Project;
use App\Models\Transaction;
use App\Services\MidtransService;
use App\Support\ApiErrorResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChargeController extends Controller
{
    public function store(StoreChargeRequest $request, MidtransService $midtransService): JsonResponse
    {
        /** @var Project $project */
        $project = $request->attributes->get('project');
        $clientOrderId = $request->string('order_id')->toString();
        $existingTransaction = Transaction::query()
            ->where('project_id', $project->id)
            ->where('client_order_id', $clientOrderId)
            ->first();

        if ($existingTransaction) {
            if (! $this->hasSameChargePayload($request, $project, $existingTransaction)) {
                return response()->json([
                    ...ApiErrorResponse::make(
                        message: 'Order ID sudah pernah digunakan dengan payload yang berbeda.',
                        code: 'order_id_conflict',
                        status: JsonResponse::HTTP_CONFLICT,
                    ),
                    'existing_transaction' => [
                        'gateway_order_id' => $existingTransaction->gateway_order_id,
                        'order_id' => $existingTransaction->client_order_id,
                    ],
                ], JsonResponse::HTTP_CONFLICT);
            }

            return $this->buildChargeResponse($project, $existingTransaction, JsonResponse::HTTP_OK, true);
        }

        try {
            $transaction = DB::transaction(function () use ($request, $project, $midtransService, $clientOrderId) {
                $transaction = Transaction::create([
                    'project_id' => $project->id,
                    'gateway_order_id' => $midtransService->generateGatewayOrderId($project->app_id),
                    'client_order_id' => $clientOrderId,
                    'amount' => $request->integer('gross_amount'),
                    'currency' => $this->resolvedCurrency($request),
                    'status' => \App\Enums\TransactionStatus::Pending,
                    'callback_status' => \App\Enums\CallbackStatus::Pending,
                    'callback_url' => $this->resolvedCallbackUrl($request, $project),
                    'customer_details' => $request->input('customer_details'),
                    'item_details' => $request->input('item_details', []),
                    'metadata' => $request->input('metadata', []),
                    'expires_at' => $request->date('expires_at'),
                ]);

                $transaction->load('project');

                $midtransResponse = $midtransService->createSnapTransaction($transaction);

                $transaction->forceFill([
                    'snap_token' => $midtransResponse['token'] ?? null,
                    'snap_redirect_url' => $midtransResponse['redirect_url'] ?? null,
                    'midtrans_payload' => $midtransResponse,
                ])->save();

                return $transaction;
            });
        } catch (QueryException $exception) {
            $raceConditionTransaction = Transaction::query()
                ->where('project_id', $project->id)
                ->where('client_order_id', $clientOrderId)
                ->first();

            if (! $raceConditionTransaction) {
                throw $exception;
            }

            if (! $this->hasSameChargePayload($request, $project, $raceConditionTransaction)) {
                return response()->json([
                    ...ApiErrorResponse::make(
                        message: 'Order ID sudah pernah digunakan dengan payload yang berbeda.',
                        code: 'order_id_conflict',
                        status: JsonResponse::HTTP_CONFLICT,
                    ),
                    'existing_transaction' => [
                        'gateway_order_id' => $raceConditionTransaction->gateway_order_id,
                        'order_id' => $raceConditionTransaction->client_order_id,
                    ],
                ], JsonResponse::HTTP_CONFLICT);
            }

            return $this->buildChargeResponse($project, $raceConditionTransaction, JsonResponse::HTTP_OK, true);
        }

        return $this->buildChargeResponse($project, $transaction, JsonResponse::HTTP_CREATED);
    }

    protected function buildChargeResponse(
        Project $project,
        Transaction $transaction,
        int $statusCode,
        bool $reused = false,
    ): JsonResponse {
        return response()->json([
            'status' => 'success',
            'project' => [
                'app_id' => $project->app_id,
                'name' => $project->project_name,
            ],
            'order_id' => $transaction->client_order_id,
            'gateway_order_id' => $transaction->gateway_order_id,
            'token' => $transaction->snap_token,
            'redirect_url' => $transaction->snap_redirect_url,
            'idempotency' => [
                'reused' => $reused,
            ],
        ], $statusCode);
    }

    protected function hasSameChargePayload(Request $request, Project $project, Transaction $transaction): bool
    {
        return $transaction->amount === $request->integer('gross_amount')
            && $transaction->currency === $this->resolvedCurrency($request)
            && (string) ($transaction->callback_url ?? '') === (string) ($this->resolvedCallbackUrl($request, $project) ?? '')
            && ($transaction->customer_details ?? []) == ($request->input('customer_details') ?? [])
            && ($transaction->item_details ?? []) == ($request->input('item_details', []) ?? [])
            && ($transaction->metadata ?? []) == ($request->input('metadata', []) ?? []);
    }

    protected function resolvedCurrency(StoreChargeRequest $request): string
    {
        return $request->string('currency')->toString() ?: (string) config('payment.currency', 'IDR');
    }

    protected function resolvedCallbackUrl(StoreChargeRequest $request, Project $project): ?string
    {
        return $request->string('custom_callback_url')->toString() ?: $project->default_callback_url;
    }
}
