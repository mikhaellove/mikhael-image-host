<?php

namespace App\Services;

use App\Models\Image;

class SlugGenerator
{
    private const SLUG_LENGTH = 12;
    private const CHARSET = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    private const MAX_ATTEMPTS = 100;

    public static function generate(): string
    {
        $attempts = 0;

        do {
            $slug = self::generateRandomSlug();
            $attempts++;

            if ($attempts >= self::MAX_ATTEMPTS) {
                throw new \RuntimeException("Failed to generate unique slug after " . self::MAX_ATTEMPTS . " attempts");
            }

        } while (Image::slugExists($slug));

        return $slug;
    }

    private static function generateRandomSlug(): string
    {
        $slug = '';
        $charsetLength = strlen(self::CHARSET);

        for ($i = 0; $i < self::SLUG_LENGTH; $i++) {
            $randomIndex = random_int(0, $charsetLength - 1);
            $slug .= self::CHARSET[$randomIndex];
        }

        return $slug;
    }
}
