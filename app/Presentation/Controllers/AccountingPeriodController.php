<?php

namespace App\Presentation\Controllers;

use App\Application\Actions\Accounting\LockAccountingPeriodAction;
use App\Application\Services\BranchAuthorizationService;
use App\Infrastructure\Models\User;
use App\Presentation\Resources\AccountingPeriodResource;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AccountingPeriodController extends Controller
{
    public function lock(
        Request $request,
        int $accountingPeriodId,
        LockAccountingPeriodAction $action,
        BranchAuthorizationService $authorization,
    ): AccountingPeriodResource {
        $user = $request->user();
        abort_unless($user instanceof User, Response::HTTP_UNAUTHORIZED);
        abort_unless(
            $authorization->hasTenantWideRole($user, 'Admin'),
            Response::HTTP_FORBIDDEN,
        );

        return new AccountingPeriodResource(
            $action->handle($accountingPeriodId, $user->getKey()),
        );
    }
}
