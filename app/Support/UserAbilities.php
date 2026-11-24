<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

class UserAbilities
{
    /** @var array<int, string> */
    public const ALL = [
        'manage-transactions',
        'view-revenue',
        'view-reports',
        'manage-website',
        'manage-employees',
    ];

    /**
     * Peta ability => boolean untuk satu user (dipakai frontend untuk menu/UX).
     *
     * @return array<string, bool>
     */
    public static function for(User $user): array
    {
        $gate = Gate::forUser($user);
        $result = [];

        foreach (self::ALL as $ability) {
            $result[$ability] = $gate->allows($ability);
        }

        return $result;
    }
}