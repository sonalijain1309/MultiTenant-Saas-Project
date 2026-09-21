<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\CreateTenantUserRequest;
use App\Http\Requests\User\UpdateTenantUserRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Subscription\SubscriptionService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class UserApiController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptionService
    ) {
    }

    /**
     * Display a listing of users scoped to the active tenant.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        $currentUser = $request->user();
        $tenant = app(TenantContext::class)->getTenant() ?? $currentUser->tenant;

        if ($currentUser->isSuperAdmin() && ! $tenant) {
            $users = User::latest()->paginate(15);
        } else {
            $users = User::where('tenant_id', $tenant?->id)->latest()->paginate(15);
        }

        return response()->json([
            'data' => UserResource::collection($users),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    /**
     * Store a newly created tenant user, subject to plan user quotas.
     */
    public function store(CreateTenantUserRequest $request): JsonResponse
    {
        Gate::authorize('create', User::class);

        $currentUser = $request->user();
        $tenant = app(TenantContext::class)->getTenant() ?? $currentUser->tenant;

        $targetRole = UserRole::from($request->input('role'));

        if ($currentUser->isSuperAdmin() && $targetRole === UserRole::SUPER_ADMIN) {
            $tenantId = null;
        } else {
            if (! $tenant) {
                return response()->json([
                    'message' => 'Cannot create tenant user: no active tenant context.',
                ], Response::HTTP_BAD_REQUEST);
            }

            // Enforce plan quota for users
            $this->subscriptionService->ensureCanCreate($tenant, 'users');
            $tenantId = $tenant->id;
        }

        $user = User::create([
            'tenant_id' => $tenantId,
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => $request->input('password'),
            'role' => $targetRole,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json([
            'message' => 'User created successfully.',
            'user' => new UserResource($user),
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified user.
     */
    public function show(User $user): JsonResponse
    {
        Gate::authorize('view', $user);

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Update the specified user.
     */
    public function update(UpdateTenantUserRequest $request, User $user): JsonResponse
    {
        Gate::authorize('update', $user);

        $data = $request->only(['name', 'email', 'is_active']);

        if ($request->filled('password')) {
            $data['password'] = $request->input('password');
        }

        if ($request->filled('role')) {
            $data['role'] = UserRole::from($request->input('role'));
        }

        $user->update($data);

        return response()->json([
            'message' => 'User updated successfully.',
            'user' => new UserResource($user->fresh()),
        ]);
    }

    /**
     * Delete the specified user.
     */
    public function destroy(User $user): JsonResponse
    {
        Gate::authorize('delete', $user);

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully.',
        ]);
    }
}
