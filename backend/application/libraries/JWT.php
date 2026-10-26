<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class JWT {
    private static $key = 'WISDOM_SECRET_KEY_2026'; // Clave secreta por defecto
    private static $alg = 'HS256';

    public static function encode($data) {
        $header = json_encode(['typ' => 'JWT', 'alg' => self::$alg]);
        $payload = json_encode(array_merge($data, ['exp' => time() + (60 * 60 * 24)])); // 24 horas validez

        $base64UrlHeader = self::base64UrlEncode($header);
        $base64UrlPayload = self::base64UrlEncode($payload);

        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, self::$key, true);
        $base64UrlSignature = self::base64UrlEncode($signature);

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    public static function extraerToken() {
        $tokenHeader = null;
        $CI =& get_instance();
        if ($CI && isset($CI->input)) {
            $headers = $CI->input->request_headers();
            if (isset($headers['Authorization'])) {
                $tokenHeader = $headers['Authorization'];
            } elseif (isset($headers['authorization'])) {
                $tokenHeader = $headers['authorization'];
            }
        }

        if (!$tokenHeader) {
            if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
                $tokenHeader = $_SERVER['HTTP_AUTHORIZATION'];
            } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                $tokenHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            } elseif (function_exists('apache_request_headers')) {
                $apacheHeaders = apache_request_headers();
                if (isset($apacheHeaders['Authorization'])) {
                    $tokenHeader = $apacheHeaders['Authorization'];
                } elseif (isset($apacheHeaders['authorization'])) {
                    $tokenHeader = $apacheHeaders['authorization'];
                }
            }
        }

        return $tokenHeader ? trim(str_ireplace('Bearer', '', $tokenHeader)) : null;
    }

    public static function decode($token = null) {
        if ($token === null || $token === '') {
            $token = self::extraerToken();
        }
        if (!$token) {
            return false;
        }

        $token = trim(str_ireplace('Bearer', '', $token));
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        list($header, $payload, $signature) = $parts;

        $validSignature = hash_hmac('sha256', $header . "." . $payload, self::$key, true);
        if (!hash_equals(self::base64UrlDecode($signature), $validSignature)) {
            return false;
        }

        $decodedPayload = json_decode(self::base64UrlDecode($payload), true);
        if (isset($decodedPayload['exp']) && $decodedPayload['exp'] < time()) {
            return false; // Token expirado
        }

        // Verificar que el usuario no haya sido desactivado en la base de datos
        if (isset($decodedPayload['id'])) {
            $CI =& get_instance();
            if (isset($CI->db)) {
                $userDb = $CI->db->select('activo')->get_where('usuarios', ['id' => $decodedPayload['id']])->row_array();
                if (!$userDb || (isset($userDb['activo']) && (int)$userDb['activo'] === 0)) {
                    return false; // Usuario inactivo / desactivado
                }
            }
        }

        return $decodedPayload;
    }

    private static function base64UrlEncode($data) {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    private static function base64UrlDecode($data) {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(str_replace(['-', '_'], ['+', '/'], $data));
    }
}
