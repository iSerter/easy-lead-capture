<?php

declare(strict_types=1);

namespace Iserter\EasyLeadCapture\Mail;

use Symfony\Component\Mailer\Mailer as SymfonyMailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

class MailerFactory
{
    public static function create(array $config): ?MailerInterface
    {
        if (empty($config['admin']['email'])) {
            return null;
        }

        $mailConfig = $config['mail'];
        $mailerType = $mailConfig['mailer'] ?? 'sendmail';

        try {
            if ($mailerType === 'smtp' || $mailerType === 'ses') {
                $transport = new EsmtpTransport(
                    $mailConfig['host'],
                    (int)$mailConfig['port'],
                    $mailConfig['encryption'] === 'tls',
                    null,
                    null
                );

                if ($mailConfig['username'] && $mailConfig['password']) {
                    $transport->setUsername($mailConfig['username']);
                    $transport->setPassword($mailConfig['password']);
                }
            } else {
                // Default to sendmail
                $transport = Transport::fromDsn('sendmail://default');
            }

            return new SymfonyMailer($transport);
        } catch (\Throwable $e) {
            // Log error or ignore? For now, if it fails to create, return null
            return null;
        }
    }
}
