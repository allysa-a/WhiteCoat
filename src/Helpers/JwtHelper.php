<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Minimal JWT encode (HS256) so the backend works without Composer/firebase-php-jwt.
 */
final class JwtHelper
{
  public static function encode(array $payload, string $secret, int $expireSeconds = 28800): string
  {
    $header = ['typ' => 'JWT', 'alg' => 'HS256'];
    $payload['iat'] = time();
    $payload['exp'] = time() + $expireSeconds;
    $seg1 = self::base64UrlEncode(json_encode($header));
    $seg2 = self::base64UrlEncode(json_encode($payload));
    $signature = hash_hmac('sha256', $seg1 . '.' . $seg2, $secret, true);
    return $seg1 . '.' . $seg2 . '.' . self::base64UrlEncode($signature);
  }

  private static function base64UrlEncode(string $data): string
  {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
  }
}
