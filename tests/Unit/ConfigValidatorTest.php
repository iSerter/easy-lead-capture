<?php

declare(strict_types=1);

namespace Iserter\EasyLeadCapture\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Iserter\EasyLeadCapture\Config\ConfigValidator;
use InvalidArgumentException;

class ConfigValidatorTest extends TestCase
{
    public function test_it_throws_if_admin_password_is_missing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Config error: 'admin.password' is required.");
        
        ConfigValidator::validate(['base_path' => '/test']);
    }

    public function test_it_merges_defaults(): void
    {
        $config = ConfigValidator::validate([
            'admin' => ['password' => 'secret']
        ]);

        $this->assertEquals('secret', $config['admin']['password']);
        $this->assertEquals('', $config['base_path']);
        $this->assertNotEmpty($config['database']['path']);
        $this->assertEquals('Thank you!', $config['on_submit']['success_headline']);
        $this->assertEquals('#4F46E5', $config['form']['colors']['primary']);
    }

    public function test_it_validates_base_path(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ConfigValidator::validate([
            'admin' => ['password' => 'secret'],
            'base_path' => 'no-slash'
        ]);
    }

    public function test_it_overrides_nested_defaults(): void
    {
        $config = ConfigValidator::validate([
            'admin' => ['password' => 'secret'],
            'on_submit' => [
                'success_headline' => 'Custom Thanks'
            ]
        ]);

        $this->assertEquals('Custom Thanks', $config['on_submit']['success_headline']);
        // Verify other keys in on_submit are still there
        $this->assertEquals('We will be in touch soon.', $config['on_submit']['success_message']);
    }

    public function test_it_validates_smtp_config_if_admin_email_present(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Config error: 'mail.host' is required for smtp/ses mailer.");

        ConfigValidator::validate([
            'admin' => ['password' => 'secret', 'email' => 'admin@example.com'],
            'mail' => ['mailer' => 'smtp']
        ]);
    }
}
