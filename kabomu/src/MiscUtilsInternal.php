<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu;

class MiscUtilsInternal {

    public static function serializeInt32BE(int $v): string {
        $dest = '';
        $dest .= chr(0xFF & ($v >> 24));
        $dest .= chr(0xFF &($v >> 16));
        $dest .= chr(0xFF & ($v >> 8));
        $dest .= chr(0xFF & $v);
        return $dest;
    }

    public static function deserializeInt32BE(string $data, int $offset): int {
        $src = [
            ord($data[$offset]),
            ord($data[$offset + 1]),
            ord($data[$offset + 2]),
            ord($data[$offset + 3]),
        ];
        $v = (($src[0] & 0xFF) << 24) | 
            (($src[1] & 0xFF) << 16) | 
            (($src[2] & 0xFF) << 8) | 
            ($src[3] & 0xFF);
        if ($v >= 2147483648) {
            $v -= 4294967296;
        }
        return $v;
    }

    public static function parseInt48(string|int $input): int {
        if (is_int($input)) {
            $n = $input;
        }
        else {
            $input = trim($input);
            if (!preg_match('/^[+-]?\d{1,20}$/', $input)) {
                throw new \InvalidArgumentException("Invalid input: $input");
            }
            $n = intval($input);
        }
        if ($n < -140_737_488_355_328 || $n > 140_737_488_355_327) {
            throw new \InvalidArgumentException("invalid 48-bit integer: $input");
        }
        return $n;
    }

    public static function parseInt32(string|int $input): int {
        if (is_int($input)) {
            $n = $input;
        }
        else {
            $input = trim($input);
            if (!preg_match('/^[+-]?\d{1,20}$/', $input)) {
                throw new \InvalidArgumentException("Invalid input: $input");
            }
            $n = intval($input);
        }
        if ($n < -2_147_483_648 || $n > 2_147_483_647) {
            throw new \InvalidArgumentException("invalid 32-bit integer: $input");
        }
        return $n;
    }

    public static function stringToBytes(string $s): string {
        return mb_convert_encoding($s, 'UTF-8');
    }

    public static function bytesToString(string $data): string {
        return mb_convert_encoding($data, mb_internal_encoding(), 'UTF-8');
    }
}