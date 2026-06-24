<?php

namespace App\Services;

use GeoIp2\Database\Reader;
use Illuminate\Support\Facades\Log;
use Jenssegers\Agent\Agent;
use Throwable;

class RequestMetadataService
{
    public function getMetadata(string $ipAddress, ?string $userAgent): array
    {
        $deviceMetadata = $this->getDeviceData($userAgent);
        $geoData = $this->getGeoData($ipAddress);

        return [
            'device' => $deviceMetadata['device'],
            'device_type' => $deviceMetadata['device_type'],
            'platform' => $deviceMetadata['platform'],
            'browser' => $deviceMetadata['browser'],
            'device_name' => $this->generateDeviceName($deviceMetadata),
            'country' => $geoData['country'],
            'region' => $geoData['region'],
            'city' => $geoData['city'],
            'timezone' => $geoData['timezone'],
        ];
    }

    private function getDeviceData(?string $userAgent): array
    {
        $agent = new Agent();
        $agent->setUserAgent($userAgent);

        $deviceType = match (true) {
            $agent->isDesktop() => 'desktop',
            $agent->isTablet() => 'tablet',
            $agent->isMobile() => 'mobile',
            default => null,
        };

        $device = $agent->device() ?: null;

        $invalidDevices = ['webkit', 'mozilla', 'gecko', 'khtml', 'trident', 'presto'];
        if ($device && in_array(strtolower($device), $invalidDevices, true)) {
            $device = null;
        }
        if ($deviceType === 'desktop') {
            $device = null;
        }

        return [
            'device_type' => $deviceType,
            'device' => $device,
            'platform' => $agent->platform() ?: null,
            'browser' => $agent->browser() ?: null,
        ];
    }

    private function getGeoData(string $ipAddress): array
    {
        if (!$this->isPublicIpAddress($ipAddress)) {
            return $this->emptyGeoData();
        }

        try {
            $reader = new Reader(storage_path('app/geo/GeoLite2-City.mmdb'));
            $record = $reader->city($ipAddress);

            return [
                'country' => $record->country->name,
                'region' => $record->mostSpecificSubdivision->name,
                'city' => $record->city->name,
                'timezone' => $record->location->timeZone,
            ];
        } catch (Throwable $e) {
            Log::warning('GeoIP lookup failed', [
                'error' => $e->getMessage(),
                'ip' => $ipAddress,
            ]);

            return $this->emptyGeoData();
        }
    }

    private function isPublicIpAddress(string $ipAddress): bool
    {
        return filter_var(
            value: $ipAddress,
            filter: FILTER_VALIDATE_IP,
            options: FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }

    private function emptyGeoData(): array
    {
        return [
            'country' => null,
            'region' => null,
            'city' => null,
            'timezone' => null,
        ];
    }

    private function generateDeviceName(array $deviceMetadata): string
    {
        $browser = $deviceMetadata['browser'] ?? 'Browser';
        $platform = $deviceMetadata['platform'];
        $device = $deviceMetadata['device'];
        $deviceType = $deviceMetadata['device_type'];

        $base = match (true) {
            !empty($platform) => "$browser on $platform",
            !empty($device) => "$browser on $device",
            !empty($deviceType) => "$browser on " . ucfirst($deviceType),
            default => $browser,
        };

        $suffix = match (true) {
            !empty($device) && !empty($platform) && $device !== $platform => $device,
            empty($device) && !empty($deviceType) => ucfirst($deviceType),
            default => null,
        };

        if ($suffix !== null) {
            $base .= " ($suffix)";
        }

        return $base;
    }
}
