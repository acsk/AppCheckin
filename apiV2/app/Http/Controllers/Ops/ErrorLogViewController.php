<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Repositories\ApplicationErrorLogRepository;
use App\Services\ApplicationErrorLogService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ErrorLogViewController extends Controller
{
    public function __construct(
        private readonly ApplicationErrorLogService $errorLogs,
        private readonly ApplicationErrorLogRepository $repository,
    ) {}

    public function index(Request $request): View
    {
        $groups = $this->errorLogs->groupedForView(150);

        return view('ops.error-logs', [
            'groups' => $groups,
            'totalEvents' => $this->repository->totalCount(),
            'tableReady' => $this->repository->tableExists(),
            'alertEmail' => config('appcheckin.error_alert_email'),
        ]);
    }

    public function show(Request $request, string $fingerprint): View
    {
        if (! preg_match('/^[a-f0-9]{64}$/', $fingerprint)) {
            abort(404);
        }

        $events = $this->errorLogs->detailsForFingerprint($fingerprint, 100);
        if ($events === []) {
            abort(404);
        }

        return view('ops.error-log-detail', [
            'fingerprint' => $fingerprint,
            'description' => $events[0]['description'] ?? '',
            'events' => $events,
            'tableReady' => $this->repository->tableExists(),
        ]);
    }
}
