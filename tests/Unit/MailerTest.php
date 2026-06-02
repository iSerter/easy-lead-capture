<?php

declare(strict_types=1);

namespace Iserter\EasyLeadCapture\Tests\Unit;

use Iserter\EasyLeadCapture\Config\ConfigValidator;
use Iserter\EasyLeadCapture\Mail\Mailer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

class MailerTest extends TestCase
{
    public function test_it_sends_confirmation_email_with_custom_template(): void
    {
        $transport = new CapturingMailer();
        $mailer = new Mailer($transport);
        $config = ConfigValidator::validate([
            'admin' => ['password' => 'secret'],
            'mail' => [
                'from_address' => 'noreply@example.com',
                'from_name' => 'Acme',
            ],
            'confirmation_email' => [
                'enabled' => true,
                'subject' => 'Welcome',
                'body_template' => '<p>Hello {name} at {email}</p>{fields}',
                'from_address' => 'hello@example.com',
                'from_name' => 'Acme Team',
            ],
        ]);

        $mailer->sendConfirmationEmail([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ], $config);

        $message = $transport->lastMessage;
        $this->assertInstanceOf(Email::class, $message);
        $this->assertEquals('Welcome', $message->getSubject());
        $this->assertEquals('jane@example.com', $message->getTo()[0]->getAddress());
        $this->assertEquals('hello@example.com', $message->getFrom()[0]->getAddress());
        $this->assertEquals('Acme Team', $message->getFrom()[0]->getName());
        $this->assertStringContainsString('Hello Jane Doe at jane@example.com', (string)$message->getHtmlBody());
        $this->assertStringContainsString('<th>Field</th><th>Value</th>', (string)$message->getHtmlBody());
    }

    public function test_it_sends_confirmation_email_with_default_template(): void
    {
        $transport = new CapturingMailer();
        $mailer = new Mailer($transport);
        $config = ConfigValidator::validate([
            'admin' => ['password' => 'secret'],
            'confirmation_email' => [
                'enabled' => true,
                'subject' => 'Thanks',
            ],
        ]);

        $mailer->sendConfirmationEmail([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ], $config);

        $message = $transport->lastMessage;
        $this->assertInstanceOf(Email::class, $message);
        $this->assertStringContainsString('You&#039;re in, Jane Doe.', (string)$message->getHtmlBody());
        $this->assertStringContainsString('Your details', (string)$message->getHtmlBody());
    }

    public function test_it_skips_confirmation_email_when_disabled(): void
    {
        $transport = new CapturingMailer();
        $mailer = new Mailer($transport);
        $config = ConfigValidator::validate([
            'admin' => ['password' => 'secret'],
        ]);

        $mailer->sendConfirmationEmail([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ], $config);

        $this->assertNull($transport->lastMessage);
    }

    public function test_it_skips_confirmation_email_without_email_field_value(): void
    {
        $transport = new CapturingMailer();
        $mailer = new Mailer($transport);
        $config = ConfigValidator::validate([
            'admin' => ['password' => 'secret'],
            'confirmation_email' => [
                'enabled' => true,
                'subject' => 'Thanks',
            ],
        ]);

        $mailer->sendConfirmationEmail([
            'name' => 'Jane Doe',
        ], $config);

        $this->assertNull($transport->lastMessage);
    }
}

class CapturingMailer implements MailerInterface
{
    public ?RawMessage $lastMessage = null;

    public function send(RawMessage $message, ?Envelope $envelope = null): void
    {
        $this->lastMessage = $message;
    }
}
