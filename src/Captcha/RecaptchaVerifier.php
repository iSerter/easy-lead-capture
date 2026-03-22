<?php

declare(strict_types=1);

namespace Iserter\EasyLeadCapture\Captcha;

use ReCaptcha\ReCaptcha;

class RecaptchaVerifier
{
    private ReCaptcha $recaptcha;

    public function __construct(string $secretKey)
    {
        $this->recaptcha = new ReCaptcha($secretKey);
    }

    /**
     * @param string $token
     * @param string|null $ip
     * @return array{success: bool, score: float|null, action: string|null, error_codes: string[]}
     */
    public function verify(string $token, ?string $ip = null): array
    {
        $response = $this->recaptcha->verify($token, $ip);

        return [
            'success' => $response->isSuccess(),
            'score' => $response->getScore(),
            'action' => $response->getAction(),
            'error_codes' => $response->getErrorCodes(),
        ];
    }
}
