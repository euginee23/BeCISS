<?php

namespace App\Console\Commands;

use App\Services\Sms\IprogSmsSender;
use App\Services\Sms\PhoneNumber;
use App\Services\Sms\SmsSender;
use Illuminate\Console\Command;

class TestSms extends Command
{
    protected $signature = 'test:sms
        {to : Recipient number, e.g. 09171234567}
        {message? : Message text}
        {--force : Skip the confirmation prompt before a real, billed send}';

    protected $description = 'Send a test SMS through the configured driver';

    public function handle(): int
    {
        $to = (string) $this->argument('to');
        $message = (string) ($this->argument('message')
            ?: 'This is a test message from '.config('app.name').'.');

        $driver = (string) config('sms.default', 'log');
        $normalised = PhoneNumber::toE164($to, (string) config('sms.country_code', '63'));

        if ($normalised === null) {
            $this->error("\"{$to}\" is not a usable phone number.");

            return 1;
        }

        $sender = app(SmsSender::class);

        $this->info("Driver:  {$driver}");
        $this->info('From:    '.config('sms.from'));
        $this->info("To:      {$normalised}");
        $this->info("Message: {$message}");

        if ($sender instanceof IprogSmsSender) {
            // A zero balance is the likeliest reason a real send goes nowhere,
            // so surface it before spending anything.
            $credits = $sender->credits();

            $this->info('Credits: '.($credits === null
                ? 'could not be read — check IPROGSMS_TOKEN'
                : rtrim(rtrim(number_format($credits, 2), '0'), '.')));

            $this->newLine();
            $this->warn('This sends a real SMS through IPROG and costs credits.');

            if (! $this->option('force') && ! $this->confirm('Send it?', true)) {
                $this->comment('Cancelled — nothing was sent.');

                return 0;
            }
        }

        try {
            $sender->send($to, $message);
        } catch (\Exception $e) {
            $this->error('Failed to send SMS: '.$e->getMessage());

            return 1;
        }

        $this->info('✓ SMS handed off successfully!');

        if ($driver === 'log') {
            $channel = (string) config('sms.drivers.log.channel', 'sms');
            $path = $channel === 'sms' ? storage_path('logs/sms.log') : 'your configured log channel';
            $this->comment("The log driver is active — the message was written to {$path}");
        }

        if ($sender instanceof IprogSmsSender) {
            $this->comment('IPROG queues messages, so delivery follows a moment later. '
                .'The message id is recorded in '.storage_path('logs/sms.log').'.');
        }

        return 0;
    }
}
