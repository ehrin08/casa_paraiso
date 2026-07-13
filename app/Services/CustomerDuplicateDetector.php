<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CustomerDuplicateDetector
{
    public function exactEmail(string $email, ?int $ignoreUserId = null): ?User
    {
        $normalized = $this->normalizeEmail($email);

        if ($normalized === '') {
            return null;
        }

        return User::query()
            ->with('customerProfile')
            ->when($ignoreUserId, fn ($query) => $query->whereKeyNot($ignoreUserId))
            ->whereRaw('LOWER(TRIM(email)) = ?', [$normalized])
            ->first();
    }

    /**
     * @return array<int, array{id: int, user_id: int, name: string, email: string, phone: ?string, customer_code: ?string, match_types: array<int, string>}>
     */
    public function likelyMatches(string $name, ?string $phone = null, ?int $ignoreUserId = null): array
    {
        $normalizedName = $this->normalizeName($name);
        $normalizedPhone = $this->normalizePhone($phone);

        if ($normalizedName === '' && $normalizedPhone === '') {
            return [];
        }

        return User::query()
            ->with('customerProfile')
            ->where('role', User::ROLE_CUSTOMER)
            ->when($ignoreUserId, fn ($query) => $query->whereKeyNot($ignoreUserId))
            ->get()
            ->filter(fn (User $user) => $user->customerProfile && ! $user->customerProfile->trashed())
            ->map(function (User $user) use ($normalizedName, $normalizedPhone): ?array {
                $matchTypes = [];

                if ($normalizedName !== '' && $this->normalizeName($user->name) === $normalizedName) {
                    $matchTypes[] = 'name';
                }

                if ($normalizedPhone !== '' && $this->normalizePhone($user->phone) === $normalizedPhone) {
                    $matchTypes[] = 'phone';
                }

                return $matchTypes === [] ? null : $this->customerPayload($user, $matchTypes);
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{match_type: string, normalized_value: string, customers: array<int, array{id: int, user_id: int, name: string, email: string, phone: ?string, customer_code: ?string}>}>
     */
    public function reviewGroups(): array
    {
        $customers = User::query()
            ->with('customerProfile')
            ->where('role', User::ROLE_CUSTOMER)
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user) => $user->customerProfile && ! $user->customerProfile->trashed());

        return collect([
            ...$this->groupsFor($customers, 'name'),
            ...$this->groupsFor($customers, 'phone'),
        ])->sortBy([
            ['match_type', 'asc'],
            ['normalized_value', 'asc'],
        ])->values()->all();
    }

    public function normalizeEmail(?string $email): string
    {
        return Str::lower(trim((string) $email));
    }

    public function normalizeName(?string $name): string
    {
        return (string) Str::of(Str::ascii((string) $name))
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish();
    }

    public function normalizePhone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        return strlen($digits) >= 7 ? $digits : '';
    }

    /**
     * @param  Collection<int, User>  $customers
     * @return array<int, array<string, mixed>>
     */
    private function groupsFor(Collection $customers, string $matchType): array
    {
        $normalizer = $matchType === 'phone' ? 'normalizePhone' : 'normalizeName';

        return $customers
            ->groupBy(fn (User $user) => $this->{$normalizer}($matchType === 'phone' ? $user->phone : $user->name))
            ->reject(fn (Collection $group, string|int $value) => (string) $value === '' || $group->count() < 2)
            ->map(fn (Collection $group, string|int $value) => [
                'match_type' => $matchType,
                'normalized_value' => (string) $value,
                'customers' => $group
                    ->map(fn (User $user) => $this->customerPayload($user))
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $matchTypes
     * @return array<string, mixed>
     */
    private function customerPayload(User $user, array $matchTypes = []): array
    {
        return [
            'id' => $user->customerProfile->id,
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'customer_code' => $user->customerProfile->customer_code,
            ...($matchTypes === [] ? [] : ['match_types' => $matchTypes]),
        ];
    }
}
