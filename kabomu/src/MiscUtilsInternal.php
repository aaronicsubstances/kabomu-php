<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu;

class MiscUtilsInternal {

    public static function serializeInt32BE(int $v): array {
        $dest = [];
        $dest[0] = 0xFF & ($v >> 24);
        $dest[1] = 0xFF &($v >> 16);
        $dest[2] = 0xFF & ($v >> 8);
        $dest[3] = 0xFF & $v;
        return $dest;
    }

    public static function deserializeInt32BE(array $src, int $offset): int {
        $v = (($src[$offset] & 0xFF) << 24) | 
            (($src[$offset + 1] & 0xFF) << 16) | 
            (($src[$offset + 2] & 0xFF) << 8) | 
            ($src[$offset + 3] & 0xFF);
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

    public static function stringToBytes(string $s): array {
        $ascii_chars = str_split(mb_convert_encoding($s, 'UTF-8'));
        return array_map(function($item) { return ord($item); }, $ascii_chars);
    }

    public static function bytesToString(array $data): string {
        $data = array_map(function($item) { return chr($item); }, $data);
        return mb_convert_encoding(join("", $data), mb_internal_encoding(), 'UTF-8');
    }
}