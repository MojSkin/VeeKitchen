<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'app' => [
                'name' => config('app.name'),
            ],
            'auth' => [
                // null for guests; customers are users too.
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role->value,
                    'roleLabel' => $user->role->label(),
                ] : null,
                'branchId' => $user?->branch_id,
            ],
            'stockAlerts' => $user?->role === UserRole::Admin
                ? fn () => $this->stockAlerts($user)
                : null,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }

    /**
     * Unread low-stock alerts feeding the admin top-bar counter.
     *
     * @return array<string, mixed>
     */
    protected function stockAlerts(User $user): array
    {
        return [
            'count' => $user->unreadNotifications()->count(),
            'items' => $user->unreadNotifications()
                ->latest()
                ->limit(6)
                ->get()
                ->map(fn ($notification) => [
                    'id' => $notification->id,
                    'title' => $notification->data['title'] ?? 'اعلان',
                    'message' => $notification->data['message'] ?? '',
                    'date' => $notification->created_at?->toISOString(),
                ])
                ->values(),
        ];
    }
}
