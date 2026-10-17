<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Local-development helper: print the last 6-digit code that was "sent" while
 * MAIL_MAILER=log, so the verification and password-reset flows can be walked
 * through without an SMTP server. Configure a real mailer and this command has
 * nothing to show — the code only ever exists in the mailbox.
 */
class ShowLastAuthCode extends Command
{
    protected $signature = 'acadealert:last-code {email? : only codes sent to this address}';

    protected $description = 'Show the most recent email code from the log (MAIL_MAILER=log only)';

    public function handle(): int
    {
        if (config('mail.default') !== 'log') {
            $this->components->warn('MAIL_MAILER is "'.config('mail.default').'", so codes are delivered by email, not logged.');

            return self::FAILURE;
        }

        // LOG_STACK=daily writes laravel-YYYY-MM-DD.log, so search the newest
        // files first rather than assuming a single laravel.log.
        $logs = collect(File::glob(storage_path('logs/laravel*.log')))
            ->sortByDesc(fn (string $file) => File::lastModified($file))
            ->values();

        if ($logs->isEmpty()) {
            $this->components->error('No log file yet in '.storage_path('logs'));

            return self::FAILURE;
        }

        $email = $this->argument('email');
        $pattern = '/AcadeAlert (password_reset|email_verification) code for (\S+): (\d{6})/';
        $found = null;

        foreach ($logs as $path) {
            foreach (array_reverse(preg_split('/\R/', (string) File::get($path)) ?: []) as $line) {
                if (preg_match($pattern, $line, $m) && (! $email || strcasecmp($m[2], $email) === 0)) {
                    $found = ['purpose' => $m[1], 'email' => $m[2], 'code' => $m[3]];
                    break 2;
                }
            }
        }

        if (! $found) {
            $this->components->warn('No code found in the log'.($email ? ' for '.$email : '').'.');

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Purpose', str_replace('_', ' ', $found['purpose']));
        $this->components->twoColumnDetail('Sent to', $found['email']);
        $this->components->twoColumnDetail('Code', '<fg=green;options=bold>'.$found['code'].'</>');

        return self::SUCCESS;
    }
}
