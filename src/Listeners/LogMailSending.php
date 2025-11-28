<?php

namespace StellarSecurity\ApplicationInsightsLaravel\Listeners;

use Illuminate\Mail\Events\MessageSending;
use StellarSecurity\ApplicationInsightsLaravel\ApplicationInsights;

class LogMailSending
{
    public function __construct(
        protected ApplicationInsights $ai
    ) {}

    public function handle(MessageSending $event): void
    {
        if (! config('stellar-ai.features.mail', true)) {
            return;
        }

        $this->ai->trackEvent('mail.sending', [
            'mail.class' => is_object($event->data['__laravel_notification'] ?? null)
                ? get_class($event->data['__laravel_notification'])
                : 'unknown',
        ]);
    }
}
