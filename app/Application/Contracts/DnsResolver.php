<?php

namespace App\Application\Contracts;

interface DnsResolver
{
    /** @return list<string> */
    public function resolve(string $host): array;
}
