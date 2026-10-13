<?php

declare(strict_types=1);

namespace App\Core;

final class Mailer
{
    public static function send(string $to, string $subject, string $body): void
    {
        $driver = (string) Config::get('mail.driver', 'log');

        if ($driver === 'log') {
            Logger::info("MAIL to={$to} subject={$subject} body={$body}");
            return;
        }

        Logger::warning('Mail driver is not configured. Password reset email was not sent.');
    }
}
