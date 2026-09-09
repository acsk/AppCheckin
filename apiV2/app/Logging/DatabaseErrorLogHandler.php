<?php

namespace App\Logging;

use App\Services\ApplicationErrorLogService;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

final class DatabaseErrorLogHandler extends AbstractProcessingHandler
{
    public function __construct(
        private readonly ApplicationErrorLogService $errorLogService,
        int|string|Level $level = Level::Error,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        try {
            $this->errorLogService->recordFromLogRecord($record);
        } catch (\Throwable $e) {
            // Não usar Log:: aqui — evitar loop no canal database_errors.
            error_log('DatabaseErrorLogHandler: '.$e->getMessage());
        }
    }
}
