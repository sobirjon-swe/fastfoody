<?php

namespace App\Enums;

enum UserRole: string
{
    case Customer = 'customer';
    case RestaurantStaff = 'restaurant_staff';
    case SuperAdmin = 'super_admin';

    /**
     * Roles that may only be created by a super admin, never by self-registration.
     */
    public function isPrivileged(): bool
    {
        return $this !== self::Customer;
    }

    /**
     * Roles whose data access is limited to a single restaurant (tenant).
     */
    public function isTenantScoped(): bool
    {
        return $this === self::RestaurantStaff;
    }
}
