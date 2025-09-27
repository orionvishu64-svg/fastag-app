<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
const AUTH_COOKIE_NAME   = 'login_user';
const AUTH_COOKIE_SECRET = 'CHANGE_ME_TO_A_LONG_RANDOM_SECRET_AT_LEAST_64_BYTES';
const AUTH_COOKIE_TTL    = 60 * 60 * 24 * 120;
function sign_token(array $payload): string { $b=base64_encode(json_encode($payload)); $sig=hash_hmac('sha256',$b,AUTH_COOKIE_SECRET); return $b.'.'.$sig; }
function verify_token(string $token): ?array {
  $parts=explode('.', $token, 2); if(count($parts)!==2) return null; [$b,$sig]=$parts;
  if(!hash_equals(hash_hmac('sha256',$b,AUTH_COOKIE_SECRET),$sig)) return null;
  $p=json_decode(base64_decode($b), true); if(!$p) return null; if(($p['exp']??0)<time()) return null; return $p;
}
