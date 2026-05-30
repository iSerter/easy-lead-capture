<?php

declare(strict_types=1);

namespace Iserter\EasyLeadCapture\Tests\Unit;

use Iserter\EasyLeadCapture\Support\SourceTracker;
use PHPUnit\Framework\TestCase;

class SourceTrackerTest extends TestCase
{
    public function test_extracts_only_allowed_params()
    {
        $query = [
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'foo' => 'bar'
        ];
        $allowed = ['utm_source', 'utm_medium', 'utm_campaign'];

        $result = SourceTracker::extractFromQuery($query, $allowed);

        $this->assertEquals([
            'utm_source' => 'google',
            'utm_medium' => 'cpc'
        ], $result);
        $this->assertArrayNotHasKey('foo', $result);
        $this->assertArrayNotHasKey('utm_campaign', $result);
    }

    public function test_sanitizes_values()
    {
        $query = [
            'utm_source' => '  google  ',
            'utm_medium' => '<script>alert(1)</script>'
        ];
        $allowed = ['utm_source', 'utm_medium'];

        $result = SourceTracker::extractFromQuery($query, $allowed);

        $this->assertEquals([
            'utm_source' => 'google',
            'utm_medium' => '&lt;script&gt;alert(1)&lt;/script&gt;'
        ], $result);
    }

    public function test_truncates_long_values()
    {
        $longValue = str_repeat('a', 300);
        $query = ['utm_source' => $longValue];
        $allowed = ['utm_source'];

        $result = SourceTracker::extractFromQuery($query, $allowed);

        $this->assertEquals(255, strlen($result['utm_source']));
        $this->assertEquals(str_repeat('a', 255), $result['utm_source']);
    }

    public function test_ignores_empty_values()
    {
        $query = [
            'utm_source' => '',
            'utm_medium' => '  '
        ];
        $allowed = ['utm_source', 'utm_medium'];

        $result = SourceTracker::extractFromQuery($query, $allowed);

        $this->assertEmpty($result);
    }

    public function test_merge_into_lead_data()
    {
        $formData = ['name' => 'John', 'email' => 'john@example.com'];
        $source = ['utm_source' => 'google'];

        $result = SourceTracker::mergeIntoLeadData($formData, $source);

        $this->assertEquals([
            'name' => 'John',
            'email' => 'john@example.com',
            '_source' => ['utm_source' => 'google']
        ], $result);
    }

    public function test_merge_does_nothing_if_source_empty()
    {
        $formData = ['name' => 'John', 'email' => 'john@example.com'];
        
        $result = SourceTracker::mergeIntoLeadData($formData, []);
        $this->assertEquals($formData, $result);
        $this->assertArrayNotHasKey('_source', $result);
    }
}
