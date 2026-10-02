<?php

declare(strict_types=1);

namespace App\Mail\Providers;

use App\Mail\Client\HostingerMailClient;
use App\Mail\Contracts\MailTransportContract;
use App\Mail\Coordinator\MailDispatchCoordinator;
use App\Mail\MailSubstrate;
use App\Mail\Workers\EmailMakingWorker;
use App\Mail\Workers\TransientTransportWorker;
use App\Mail\Workers\TransportWorker;
use App\Shared\Contracts\ConfigurationContract;
use Illuminate\Support\ServiceProvider;

/**
 * MailServiceProvider — registration for the standalone mail package.
 *
 * Pinned boot-order position: Queue → Payments → **Mail** → Cms. The
 * package's inbound data/bookkeeping ports (App\Mail\Contracts\*) are
 * bound on the Payments side (App\Payments\Mail\* adapters), which is
 * why this provider boots after Payments.
 */
final class MailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The segregated SDK touchpoint — built from env config only.
        $this->app->singleton(HostingerMailClient::class, function ($app): HostingerMailClient {
            /** @var ConfigurationContract $config */
            $config = $app->make(ConfigurationContract::class);

            return new HostingerMailClient(
                apiToken: (string) $config->get('hostinger-mail.api_token', ''),
                baseUrl: (string) $config->get('hostinger-mail.api_base_url', 'https://api.mail.hostinger.com'),
            );
        });

        // The mother file — core substrate logic + canonical email composition.
        $this->app->singleton(MailSubstrate::class);

        // Provider seam: any future mail provider is one more adapter here.
        $this->app->bind(MailTransportContract::class, MailSubstrate::class);

        // Workers (stateless boundaries; auto-resolved but pinned as
        // singletons so the package graph is explicit).
        $this->app->singleton(TransientTransportWorker::class);
        $this->app->singleton(EmailMakingWorker::class);
        $this->app->singleton(TransportWorker::class);

        // The seam between the payments system and this package.
        $this->app->singleton(MailDispatchCoordinator::class);
    }

    public function boot(): void
    {
        //
    }
}
