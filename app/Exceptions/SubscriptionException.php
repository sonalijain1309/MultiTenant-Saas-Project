<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionException extends Exception
{
    public function __construct(
        string $message = 'Subscription is not in an operational state.',
        int $statusCode = Response::HTTP_FORBIDDEN
    ) {
        parent::__construct($message, $statusCode);
    }

    public static function pastDue(): self
    {
        return new self('Subscription is past due. Please update billing to create resources.', Response::HTTP_FORBIDDEN);
    }

    public static function canceled(): self
    {
        return new self('Subscription has been canceled. Please renew your plan to create resources.', Response::HTTP_FORBIDDEN);
    }

    public static function missing(): self
    {
        return new self('No active subscription found for tenant.', Response::HTTP_FORBIDDEN);
    }

    public function render(Request $request): JsonResponse|Response
    {
        return response()->json([
            'message' => $this->getMessage(),
        ], $this->getCode() ?: Response::HTTP_FORBIDDEN);
    }
}
