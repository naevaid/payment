<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChargeRequest;
use App\Models\Project;
use App\Models\Transaction;
use App\Services\MidtransService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ChargeController extends Controller
{
    public function store(StoreChargeRequest $request, MidtransService $midtransService): JsonResponse
    {
        /** @var Project $project */
        $project = $request->attributes->get('project');

        $transaction = DB::transaction(function () use ($request, $project, $midtransService) {
            $transaction = Transaction::create([
                'project_id' => $project->id,
                'gateway_order_id' => $midtransService->generateGatewayOrderId($project->app_id),
                'client_order_id' => $request->string('order_id')->toString(),
                'amount' => $request->integer('gross_amount'),
                'currency' => $request->string('currency')->toString() ?: config('payment.currency', 'IDR'),
                'status' => \App\Enums\TransactionStatus::Pending,
                'callback_status' => \App\Enums\CallbackStatus::Pending,
                'callback_url' => $request->string('custom_callback_url')->toString() ?: $project->default_callback_url,
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
        ], JsonResponse::HTTP_CREATED);
    }
}
