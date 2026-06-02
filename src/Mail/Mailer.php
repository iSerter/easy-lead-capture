<?php

declare(strict_types=1);

namespace Iserter\EasyLeadCapture\Mail;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class Mailer
{
    public function __construct(private readonly ?MailerInterface $mailer) {}

    public function sendLeadNotification(array $leadData, array $config): void
    {
        if ($this->mailer === null || empty($config['admin']['email'])) {
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

            // Source tracking
            $sourceData = $leadData['_source'] ?? [];
            if (!empty($sourceData)) {
                $html .= '<h3 style="margin-top: 20px;">Source Tracking</h3>';
                $html .= '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse; width: 100%;">';
                $html .= '<thead><tr style="background-color: #f3f4f6;"><th>Param</th><th>Value</th></tr></thead>';
                $html .= '<tbody>';
                foreach ($sourceData as $key => $value) {
                    $label = ucwords(str_replace(['utm_', '_'], ['', ' '], $key));
                    $html .= sprintf(
                        '<tr><td style="font-weight: bold; width: 30%%;">%s</td><td>%s</td></tr>',
                        htmlspecialchars($label),
                        htmlspecialchars((string)$value)
                    );
                }
                $html .= '</tbody></table>';
            }

            $email->html($html);

            $this->mailer->send($email);
        } catch (\Throwable $e) {
            // Silently fail as per requirements (lead is already stored)
        }
    }

    public function sendConfirmationEmail(array $leadData, array $config): void
    {
        if ($this->mailer === null || !($config['confirmation_email']['enabled'] ?? false)) {
            return;
        }

        try {
            $recipient = $this->getLeadEmailAddress($leadData, $config);
            if ($recipient === null) {
                return;
            }

            $confirmationConfig = $config['confirmation_email'];
            $fromAddress = $confirmationConfig['from_address'] ?? $config['mail']['from_address'];
            $fromName = $confirmationConfig['from_name'] ?? $config['mail']['from_name'];
            $subject = $confirmationConfig['subject'];
            $html = $this->renderConfirmationBody($leadData, $config);

            $email = (new Email())
                ->from(new Address($fromAddress, $fromName))
                ->to($recipient)
                ->subject($subject)
                ->html($html);

            $this->mailer->send($email);
        } catch (\Throwable $e) {
            // Silently fail as per requirements (lead is already stored)
        }
    }

    private function renderConfirmationBody(array $leadData, array $config): string
    {
        $bodyTemplate = $config['confirmation_email']['body_template'] ?? null;

        if ($bodyTemplate !== null) {
            return strtr($bodyTemplate, [
                '{name}' => $this->escape($this->getLeadName($leadData, $config) ?? ''),
                '{email}' => $this->escape($this->getLeadEmailAddress($leadData, $config) ?? ''),
                '{fields}' => $this->renderFieldsTable($leadData, $config),
            ]);
        }

        ob_start();
        include dirname(__DIR__) . '/Views/emails/confirmation.php';
        return (string)ob_get_clean();
    }

    private function getLeadEmailAddress(array $leadData, array $config): ?string
    {
        foreach ($config['form']['fields'] ?? [] as $id => $field) {
            if (($field['field_type'] ?? 'text') !== 'email') {
                continue;
            }

            $value = $leadData[$id] ?? null;
            if (is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return $value;
            }
        }

        return null;
    }

    private function getLeadName(array $leadData, array $config): ?string
    {
        if (isset($leadData['name']) && is_scalar($leadData['name']) && trim((string)$leadData['name']) !== '') {
            return (string)$leadData['name'];
        }

        foreach ($config['form']['fields'] ?? [] as $id => $field) {
            $label = strtolower((string)($field['label'] ?? ''));
            if (!str_contains($id, 'name') && !str_contains($label, 'name')) {
                continue;
            }

            $value = $leadData[$id] ?? null;
            if (is_scalar($value) && trim((string)$value) !== '') {
                return (string)$value;
            }
        }

        return null;
    }

    private function renderFieldsTable(array $leadData, array $config): string
    {
        $html = '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse; width: 100%;">';
        $html .= '<thead><tr style="background-color: #f3f4f6;"><th>Field</th><th>Value</th></tr></thead>';
        $html .= '<tbody>';

        foreach ($config['form']['fields'] ?? [] as $id => $field) {
            $value = $leadData[$id] ?? '-';
            if (is_array($value)) {
                $value = implode('; ', $value);
            }

            $html .= sprintf(
                '<tr><td style="font-weight: bold; width: 30%%;">%s</td><td>%s</td></tr>',
                $this->escape((string)($field['label'] ?? $id)),
                nl2br($this->escape((string)$value))
            );
        }

        $html .= '</tbody></table>';

        return $html;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}
