<?php

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Str;

//
// cryptoJsAesDecrypt
//
if (!function_exists('cryptoJsAesDecrypt')) {
  /**
   * @param  string   $secret
   * @param  array    $data
   * @return string
   */
  function cryptoJsAesDecrypt(string $secret, array $data)
  {
    try {
      $salt = hex2bin($data['s']);
      $iv   = hex2bin($data['iv']);
    } catch(\Exception $e) {
      return 123;
    }
    $ct = base64_decode($data['ct']);
    $concated = $secret . $salt;
    $md5 = [];
    $md5[0] = md5($concated, true);
    $result = $md5[0];
    for ($i = 1; $i < 3; $i++) {
      $md5[$i] = md5($md5[$i - 1] . $concated, true);
      $result .= $md5[$i];
    }
    return json_decode(openssl_decrypt(base64_decode(
      $data['ct']), 'aes-256-cbc', substr($result, 0, 32), true, $iv),
      true
    );
  }
}
//
// rawOrBase64
//
if (!function_exists('rawOrBase64')) {
  /**
   * @param  string   $string
   * @return string   $string
   */
  function rawOrBase64(?string $string): ?string
  {
    if (Str::startsWith((string) $string, $prefix = 'base64:')) {
      $string = base64_decode(Str::after($string, $prefix));
    }
    return $string;
  }
}
//
// encrypter
//
if (!function_exists('encrypter')) {
  /**
   * @param  string                            $key
   * @param  string                            $cipher
   * @return \Illuminate\Encryption\Encrypter
   */
  function encrypter(string $key, string $cipher = 'aes-256-cbc'): Encrypter
  {
    return new Encrypter(rawOrBase64($key), $cipher);
  }
}
