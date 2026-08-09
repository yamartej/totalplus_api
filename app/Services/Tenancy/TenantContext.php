<?php

namespace App\Services\Tenancy;

use App\Models\User;
use App\Services\Authorization\PermissionService;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TenantContext
{
    private PermissionService $permissions;

    public function __construct(PermissionService $permissions)
    {
        $this->permissions = $permissions;
    }

    public function canCrossCompany(User $user): bool
    {
        return $this->permissions->userHasPermission(
            $user,
            'tenant.cross_company'
        );
    }

    /**
     * Resolve the company that the request is allowed to operate on.
     *
     * Company-bound users:
     * - always use their own company_id
     * - a different requested company_id is rejected with 403
     *
     * Global users:
     * - require tenant.cross_company
     * - may optionally select a company
     * - null means an unscoped read only when the caller allows it
     */
    public function resolveCompanyId(
        User $user,
        $requestedCompanyId = null,
        bool $requireForGlobalUser = false
    ): ?int {
        $requested = $this->normalizeCompanyId($requestedCompanyId);

        if ($user->company_id !== null) {
            $ownCompanyId = (int) $user->company_id;

            if ($requested !== null && $requested !== $ownCompanyId) {
                throw new HttpException(
                    403,
                    'Cross-company access is forbidden.'
                );
            }

            return $ownCompanyId;
        }

        if (!$this->canCrossCompany($user)) {
            throw new HttpException(
                403,
                'User is not assigned to a company.'
            );
        }

        if ($requireForGlobalUser && $requested === null) {
            throw new HttpException(
                422,
                'company_id is required for this operation.'
            );
        }

        return $requested;
    }

    public function scope(
        Builder $query,
        User $user,
        $requestedCompanyId = null,
        string $column = 'company_id'
    ): Builder {
        $companyId = $this->resolveCompanyId(
            $user,
            $requestedCompanyId
        );

        if ($companyId !== null) {
            $query->where($column, $companyId);
        }

        return $query;
    }

    private function normalizeCompanyId($companyId): ?int
    {
        if ($companyId === null || $companyId === '') {
            return null;
        }

        if (!is_numeric($companyId)) {
            throw new HttpException(422, 'Invalid company_id.');
        }

        return (int) $companyId;
    }
}
