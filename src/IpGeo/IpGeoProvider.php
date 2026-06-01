<?php

declare(strict_types=1);

namespace Iserter\EasyLeadCapture\IpGeo;

interface IpGeoProvider
{
    public function lookup(string $ip): ?array;
}
