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

    public function test_data_dir_maps_to_database_path(): void
    {
        $config = ConfigValidator::validate([
            'admin' => ['password' => 'secret'],
            'data_dir' => '/tmp/my-app-data',
        ]);

        $this->assertEquals('/tmp/my-app-data/leads.db', $config['database']['path']);
    }

    public function test_data_dir_strips_trailing_slash(): void
    {
        $config = ConfigValidator::validate([
            'admin' => ['password' => 'secret'],
            'data_dir' => '/tmp/my-app-data/',
        ]);

        $this->assertEquals('/tmp/my-app-data/leads.db', $config['database']['path']);
    }

    public function test_explicit_database_path_takes_precedence_over_data_dir(): void
    {
        $config = ConfigValidator::validate([
            'admin' => ['password' => 'secret'],
            'data_dir' => '/tmp/ignored',
            'database' => ['path' => '/tmp/explicit/custom.db'],
        ]);

        $this->assertEquals('/tmp/explicit/custom.db', $config['database']['path']);
    }

    public function test_data_dir_is_not_in_final_config(): void
    {
        $config = ConfigValidator::validate([
            'admin' => ['password' => 'secret'],
            'data_dir' => '/tmp/my-app-data',
        ]);

        $this->assertArrayNotHasKey('data_dir', $config);
    }

    public function test_default_database_path_when_no_data_dir(): void
    {
        $config = ConfigValidator::validate([
            'admin' => ['password' => 'secret'],
        ]);

        $this->assertStringEndsWith('/data/leads.db', $config['database']['path']);
    }

    public function test_it_validates_field_labels(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Field 'broken' must have a 'label'");

        ConfigValidator::validate([
            'admin' => ['password' => 'secret'],
            'form' => [
                'fields' => [
                    'broken' => ['required' => true],
                ],
            ],
        ]);
    }

    public function test_it_validates_ping_api_when_enabled(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('api_endpoint');

        ConfigValidator::validate([
            'admin' => ['password' => 'secret'],
            'on_submit' => [
                'ping_api' => ['enabled' => true],
            ],
        ]);
    }

    public function test_ping_api_defaults_to_disabled(): void
    {
        $config = ConfigValidator::validate([
            'admin' => ['password' => 'secret'],
        ]);

        $this->assertFalse($config['on_submit']['ping_api']['enabled']);
    }

    public function test_captcha_defaults_to_disabled(): void
    {
        $config = ConfigValidator::validate([
            'admin' => ['password' => 'secret'],
        ]);

        $this->assertFalse($config['captcha']['enabled']);
    }

    public function test_it_validates_captcha_keys_when_enabled(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('recaptcha_site_key');

        ConfigValidator::validate([
            'admin' => ['password' => 'secret'],
            'captcha' => ['enabled' => true],
        ]);
    }

    public function test_custom_form_fields_merge_with_defaults(): void
    {
        $config = ConfigValidator::validate([
            'admin' => ['password' => 'secret'],
            'form' => [
                'fields' => [
                    'email' => ['label' => 'Work Email', 'required' => true, 'field_type' => 'email'],
                    'phone' => ['label' => 'Phone', 'required' => false],
                    'message' => ['label' => 'Message', 'field_type' => 'textarea'],
                ],
            ],
        ]);

        // Default 'name' field is kept, user fields are merged in
        $this->assertCount(4, $config['form']['fields']);
        $this->assertArrayHasKey('name', $config['form']['fields']);
        $this->assertEquals('Work Email', $config['form']['fields']['email']['label']);
        $this->assertEquals('textarea', $config['form']['fields']['message']['field_type']);
    }
}
