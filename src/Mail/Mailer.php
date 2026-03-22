<?php

declare(strict_types=1);

namespace Iserter\EasyLeadCapture\Mail;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class Mailer
{
    public function __construct(private readonly ?MailerInterface $mailer) {}

    public function sendLeadNotification(array $leadData, array $config): void
    {
        if ($this->mailer === null) {
            return;
        }

        try {
            $adminEmail = $config['admin']['email'];
            $fromAddress = $config['mail']['from_address'];
            $fromName = $config['mail']['from_name'];
            $fields = $config['form']['fields'];

            // Find an identifier for the subject (name or email)
            $identifier = $leadData['name'] ?? $leadData['email'] ?? 'New Lead';
            if (is_array($identifier)) {
                $identifier = implode(', ', $identifier);
            }

            $email = (new Email())
                ->from(sprintf('%s <%s>', $fromName, $fromAddress))
                ->to($adminEmail)
                ->subject(sprintf('New lead from %s', $identifier));

            $html = '<h2>New Lead Details</h2>';
            $html .= '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse; width: 100%;">';
            $html .= '<thead><tr style="background-color: #f3f4f6;"><th>Field</th><th>Value</th></tr></thead>';
            $html .= '<tbody>';

            foreach ($fields as $id => $field) {
                $value = $leadData[$id] ?? '-';
                if (is_array($value)) {
                    $value = implode('; ', $value);
                }
                $html .= sprintf(
                    '<tr><td style="font-weight: bold; width: 30%%;">%s</td><td>%s</td></tr>',
                    htmlspecialchars($field['label']),
                    nl2br(htmlspecialchars((string)$value))
                );
            }

            $html .= '</tbody></table>';

            $email->html($html);

            $this->mailer->send($email);
        } catch (\Throwable $e) {
            // Silently fail as per requirements (lead is already stored)
        }
    }
}
