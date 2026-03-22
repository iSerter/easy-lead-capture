<?php

declare(strict_types=1);

namespace Iserter\EasyLeadCapture\Support;

class DeferredTaskRunner
{
    private array $tasks = [];

    public function defer(callable $task): void
    {
        $this->tasks[] = $task;
    }

    public function run(): void
    {
        if (empty($this->tasks)) {
            return;
        }

        // Prevent script from aborting when client disconnects
        ignore_user_abort(true);
        set_time_limit(60);

        // Flush all output buffers so the response body is sent
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        flush();

        // SAPI-specific accelerators (close connection immediately)
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();           // PHP-FPM
        } elseif (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();          // LiteSpeed
        }

        // Execute all deferred tasks
        foreach ($this->tasks as $task) {
            try {
                $task();
            } catch (\Throwable $e) {
                // Silently continue - in a real app, we might log this
                error_log("Deferred task failed: " . $e->getMessage());
            }
        }
    }
}
