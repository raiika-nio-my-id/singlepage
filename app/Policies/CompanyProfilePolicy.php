<?php

namespace App\Policies;

use App\Models\CompanyProfile;
use App\Models\User;

class CompanyProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isClient();
    }

    public function view(User $user, CompanyProfile $companyProfile): bool
    {
        return $this->ownsOrAdministers($user, $companyProfile);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, CompanyProfile $companyProfile): bool
    {
        return $this->ownsOrAdministers($user, $companyProfile);
    }

    public function updateSubdomain(User $user, CompanyProfile $companyProfile): bool
    {
        return $user->isAdmin() && $this->ownsOrAdministers($user, $companyProfile);
    }

    public function updateCredentials(User $user, CompanyProfile $companyProfile): bool
    {
        return $user->isAdmin() && $this->ownsOrAdministers($user, $companyProfile);
    }

    public function updateExpiration(User $user, CompanyProfile $companyProfile): bool
    {
        return $user->isAdmin() && $this->ownsOrAdministers($user, $companyProfile);
    }

    public function updateFooterVisibility(User $user, CompanyProfile $companyProfile): bool
    {
        return $user->isAdmin() && $this->ownsOrAdministers($user, $companyProfile);
    }

    public function manageSettings(User $user): bool
    {
        return $user->isAdmin();
    }

    private function ownsOrAdministers(User $user, CompanyProfile $companyProfile): bool
    {
        return $user->isAdmin()
            || ($user->isClient() && $companyProfile->user_id === $user->id);
    }
}
