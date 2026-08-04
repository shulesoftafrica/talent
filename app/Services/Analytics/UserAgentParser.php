<?php

namespace App\Services\Analytics;

/**
 * Deliberately simple regex-based UA parsing — good enough for
 * device-type/browser/OS analytics without pulling in a full UA-database
 * dependency (those need periodic updates to stay accurate anyway, and
 * "roughly right" is all marketing analytics needs here).
 */
class UserAgentParser
{
    /**
     * @return array{device_type: string, browser: string, platform: string}
     */
    public function parse(?string $userAgent): array
    {
        $ua = (string) $userAgent;

        if ($ua === '') {
            return ['device_type' => 'unknown', 'browser' => 'unknown', 'platform' => 'unknown'];
        }

        return [
            'device_type' => $this->deviceType($ua),
            'browser' => $this->browser($ua),
            'platform' => $this->platform($ua),
        ];
    }

    private function deviceType(string $ua): string
    {
        if (preg_match('/bot|crawl|spider|slurp|facebookexternalhit|whatsapp/i', $ua)) {
            return 'bot';
        }

        if (preg_match('/iPad|Android(?!.*Mobile)|Tablet|Kindle|Silk/i', $ua)) {
            return 'tablet';
        }

        if (preg_match('/Mobi|iPhone|iPod|Android.*Mobile|BlackBerry|Windows Phone/i', $ua)) {
            return 'mobile';
        }

        return 'desktop';
    }

    private function browser(string $ua): string
    {
        return match (true) {
            (bool) preg_match('/Edg\//i', $ua) => 'Edge',
            (bool) preg_match('/OPR\/|Opera/i', $ua) => 'Opera',
            (bool) preg_match('/CriOS|Chrome/i', $ua) => 'Chrome',
            (bool) preg_match('/FxiOS|Firefox/i', $ua) => 'Firefox',
            (bool) preg_match('/SamsungBrowser/i', $ua) => 'Samsung Internet',
            (bool) preg_match('/Version\/.*Safari/i', $ua) => 'Safari',
            (bool) preg_match('/MSIE|Trident/i', $ua) => 'Internet Explorer',
            default => 'Other',
        };
    }

    private function platform(string $ua): string
    {
        return match (true) {
            (bool) preg_match('/Windows/i', $ua) => 'Windows',
            (bool) preg_match('/iPhone|iPad|iPod/i', $ua) => 'iOS',
            (bool) preg_match('/Mac OS X/i', $ua) => 'macOS',
            (bool) preg_match('/Android/i', $ua) => 'Android',
            (bool) preg_match('/Linux/i', $ua) => 'Linux',
            default => 'Other',
        };
    }
}
