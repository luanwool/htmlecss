<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\WebhookIntake;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $gateway, PaymentGatewayManager $gateways, WebhookIntake $intake): JsonResponse
    {
        try {
            $driver = $gateways->driver($gateway);
        } catch (InvalidArgumentException) {
            return response()->json(['error' => 'unknown_gateway'], 404);
        }
        $result = $intake->receive($driver, $request);

        // 401 em assinatura inválida; 200 nos demais casos para o provedor não reenviar indefinidamente.
        return response()->json(['result' => $result], $result === 'invalid_signature' ? 401 : 200);
    }
}
