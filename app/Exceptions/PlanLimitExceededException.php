<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PlanLimitExceededException extends Exception
{
    public function __construct(
        protected string $resource,
        protected int $current,
        protected int $limit,
        protected string $planName,
        ?string $message = null
    ) {
        $singular = rtrim($this->resource, 's');
        $label = ucfirst($singular);
        $msg = $message ?? "{$label} limit reached for your current plan.";
        parent::__construct($msg, Response::HTTP_FORBIDDEN);
    }

    public function getResource(): string
    {
        return $this->resource;
    }

    public function getCurrent(): int
    {
        return $this->current;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getPlanName(): string
    {
        return $this->planName;
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render(Request $request): JsonResponse|Response
    {
        if ($request->expectsJson() || $request->is('api/*') || $request->is('_test/*')) {
            return response()->json([
                'message' => $this->getMessage(),
                'resource' => $this->resource,
                'current' => $this->current,
                'limit' => $this->limit,
                'plan' => $this->planName,
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->view('errors.plan-limit', [
            'exception' => $this,
            'message' => $this->getMessage(),
            'resource' => $this->resource,
            'current' => $this->current,
            'limit' => $this->limit,
            'plan' => $this->planName,
        ], Response::HTTP_FORBIDDEN);
    }
}
