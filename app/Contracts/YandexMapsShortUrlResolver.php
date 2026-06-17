<?php

namespace App\Contracts;

interface YandexMapsShortUrlResolver
{
    public function resolve(string $url): string;
}
