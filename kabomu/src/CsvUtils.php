<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu;

class CsvUtils {
    private const TOKEN_EOI = -1;
    private const TOKEN_COMMA = 1;
    private const TOKEN_QUOTE = 2;
    private const TOKEN_CRLF = 3;
    private const TOKEN_LF = 4;
    private const TOKEN_CR = 5;

    private static function locateNextToken(string $csv, int $start,
            bool $insideQuotedValue, array &$tokenInfo) {
        // set to end of input by default
        $tokenInfo[0] = self::TOKEN_EOI;
        $tokenInfo[1] = -1;
        $csv_length = strlen($csv);
        for ($i = $start; $i < $csv_length; $i++) {
            $c = $csv[$i];
            if (!$insideQuotedValue && $c === ',') {
                $tokenInfo[0] = self::TOKEN_COMMA;
                $tokenInfo[1] = $i;
                return TRUE;
            }
            if (!$insideQuotedValue && $c === "\n") {
                $tokenInfo[0] = self::TOKEN_LF;
                $tokenInfo[1] = $i;
                return TRUE;
            }
            if (!$insideQuotedValue && $c === "\r") {
                if ($i + 1 < $csv_length && $csv[$i + 1] === "\n") {
                    $tokenInfo[0] = self::TOKEN_CRLF;
                }
                else {
                    $tokenInfo[0] = self::TOKEN_CR;
                }
                $tokenInfo[1] = $i;
                return TRUE;
            }
            if ($insideQuotedValue && $c === '"') {
                if ($i + 1 < $csv_length && $csv[$i + 1] === '"') {
                    // skip quote pair.
                    $i++;
                }
                else {
                    $tokenInfo[0] = self::TOKEN_QUOTE;
                    $tokenInfo[1] = $i;
                    return TRUE;
                }
            }
        }
        return FALSE;
    }

    /**
     * Parses a CSV string.
     * @param string $csv the csv string to parse
     * @return array CSV parse results as a list of rows, in which each row is represented as a list of values
     * corresponding to the row's columns.
     * @throws \InvalidArgumentException If an error occurs
     */
    public static function & deserialize(string $csv): array {
        $parsedCsv = array();
        $currentRow = array();
        $nextValueStartIdx = 0;
        $isCommaTheLastSeparatorSeen = FALSE;
        $tokenInfo = array(0, 0);
        $csv_length = strlen($csv);
        while ($nextValueStartIdx < $csv_length) {
            // use to detect infinite looping
            $savedNextValueStartIdx = $nextValueStartIdx;

            // look for comma, quote or newline, whichever comes first.
            $newlineLen = 1;
            $tokenIsNewline = FALSE;
            $isCommaTheLastSeparatorSeen = FALSE;

            $nextValueEndIdx = NULL;
            $tokenType = NULL;

            // only respect quote separator at the very beginning
            // of parsing a column value
            if ($csv[$nextValueStartIdx] === '"') {
                $tokenType = self::TOKEN_QUOTE;
                // locate ending quote, while skipping over
                // double occurences of quotes.
                if (!self::locateNextToken($csv, $nextValueStartIdx + 1, TRUE, $tokenInfo)) {
                    throw self::createCsvParseError(count($parsedCsv), count($currentRow),
                        "ending double quote not found");
                }
                $nextValueEndIdx = $tokenInfo[1] + 1;
            }
            else {
                self::locateNextToken($csv, $nextValueStartIdx, FALSE, $tokenInfo);
                $tokenType = $tokenInfo[0];
                if ($tokenType === self::TOKEN_COMMA) {
                    $nextValueEndIdx = $tokenInfo[1];
                    $isCommaTheLastSeparatorSeen = TRUE;
                }
                else if ($tokenType === self::TOKEN_LF || $tokenType === self::TOKEN_CR) {
                    $nextValueEndIdx = $tokenInfo[1];
                    $tokenIsNewline = TRUE;
                }
                else if ($tokenType === self::TOKEN_CRLF) {
                    $nextValueEndIdx = $tokenInfo[1];
                    $tokenIsNewline = TRUE;
                    $newlineLen = 2;
                }
                else if ($tokenType === self::TOKEN_EOI) {
                    $nextValueEndIdx = $csv_length;
                }
                else {
                    throw new \UnsupportedOperationException("unexpected token type: " . $tokenType);
                }
            }

            // create new value for current row,
            // but skip empty values between newlines, or between BOI and newline.
            if ($nextValueStartIdx < $nextValueEndIdx || !$tokenIsNewline || !empty($currentRow)) {
                try {
                    $nextValue = self::unescapeValue(substr($csv, $nextValueStartIdx,
                        $nextValueEndIdx - $nextValueStartIdx));
                }
                catch (\InvalidArgumentException $ex) {
                    throw self::createCsvParseError(count($parsedCsv), count($currentRow), $ex->getMessage());
                }
                $currentRow[] = $nextValue;
            }

            // advance input pointer.
            if ($tokenType === self::TOKEN_COMMA) {
                $nextValueStartIdx = $nextValueEndIdx + 1;
            }
            else if ($tokenType === self::TOKEN_QUOTE) {
                // validate that character after quote is EOI, comma or newline.
                $nextValueStartIdx = $nextValueEndIdx;
                if ($nextValueStartIdx < $csv_length) {
                    $c = $csv[$nextValueStartIdx];
                    if ($c === ',') {
                        $isCommaTheLastSeparatorSeen = TRUE;
                        $nextValueStartIdx++;
                    }
                    else if ($c === "\n" || $c === "\r") {
                        $parsedCsv[] = &$currentRow;
                        unset($currentRow);
                        $currentRow = [];
                        if ($c === "\r" && ($nextValueStartIdx + 1) < $csv_length &&
                                $csv[$nextValueStartIdx + 1] === "\n") {
                            $nextValueStartIdx += 2;
                        }
                        else {
                            $nextValueStartIdx++;
                        }
                    }
                    else {
                        throw self::createCsvParseError(count($parsedCsv), count($currentRow),
                            "unexpected character '$c' found at beginning");
                    }
                }
                else {
                    // leave to aftermath processing.
                }
            }
            else if ($tokenIsNewline) {
                $parsedCsv[] = &$currentRow;
                unset($currentRow);
                $currentRow = [];
                $nextValueStartIdx = $nextValueEndIdx + $newlineLen;
            }
            else {
                // leave to aftermath processing.
                $nextValueStartIdx = $nextValueEndIdx;
            }

            // ensure input pointer has advanced.
            if ($savedNextValueStartIdx >= $nextValueStartIdx) {
                throw self::createCsvParseError(count($parsedCsv), count($currentRow),
                    "algorithm bug detected as parsing didn't make an advance. Potential for infinite " .
                    "looping.");
            }
        }

        // generate empty value for case of trailing comma
        if ($isCommaTheLastSeparatorSeen) {
            $currentRow[] = "";
        }

        // add any leftover values to parsed csv rows.
        if (!empty($currentRow)) {
            $parsedCsv[] = &$currentRow;
        }

        return $parsedCsv;
    }

    private static function createCsvParseError(int $row, int $column,
            string $errorMessage): \InvalidArgumentException {
        return new \InvalidArgumentException(sprintf(
            "CSV parse error at row %s column %s: %s",
                $row + 1, $column + 1, $errorMessage));
    }

    /**
     * Generates a CSV string.
     * @param array $rows Data for CSV generation. Each row is a list whose entries will be treated as the values of
     * columns in the row. Also no row is treated specially.
     * @return string CSV string corresponding to rows
     */
    public static function serialize(array $rows): string {
        $csvBuilder = [];
        foreach ($rows as $row) {
            $addCommaSeparator = FALSE;
            foreach ($row as $value) {
                if ($addCommaSeparator) {
                    $csvBuilder[] = ",";
                }
                $csvBuilder[] = self::escapeValue($value);
                $addCommaSeparator = TRUE;
            }
            $csvBuilder[] = "\n";
        }
        return join("", $csvBuilder);
    }

    /**
     * Escapes a CSV value. Note that empty strings are always escaped as two double quotes.
     * @param string $raw CSV value to escape.
     * @return string Escaped CSV value.
     */
    public static function escapeValue(string $raw): string {
        if (!self::doesValueContainSpecialCharacters($raw)) {
            // escape empty strings with two double quotes to resolve ambiguity
            // between an empty row and a row containing an empty string - otherwise both
            // serialize to the same CSV output.
            return !$raw ? "\"\"" : $raw;
        }
        return '"' . str_replace("\"", "\"\"", $raw) . '"';
    }

    /**
     * Reverses the escaping of a CSV value.
     * @param string $escaped CSV escaped value.
     * @return string CSV value which equals escaped argument when escaped.
     * @throws \InvalidArgumentException If the escaped argument is an
     * invalid escaped value.
     */
    public static function unescapeValue(string $escaped): string {
        if (!self::doesValueContainSpecialCharacters($escaped)) {
            return $escaped;
        }
        $escaped_length = strlen($escaped);
        if ($escaped_length < 2 || !str_starts_with($escaped, "\"") || !str_ends_with($escaped, "\"")) {
            throw new \InvalidArgumentException("missing enclosing double quotes around csv value: " . $escaped);
        }
        $unescaped = [];
        for ($i = 1; $i < $escaped_length - 1; $i++) {
            $c = $escaped[$i];
            $unescaped[] = $c;
            if ($c === '"') {
                if ($i === $escaped_length - 2 || $escaped[$i + 1] !== '"') {
                    throw new \InvalidArgumentException("unescaped double quote found in csv value: " . $escaped);
                }
                $i++;
            }
        }
        return implode("", $unescaped);
    }

    private static function doesValueContainSpecialCharacters(string $s): bool {
        $s_length = strlen($s);
        for ($i = 0; $i < $s_length; $i++) {
            $c = $s[$i];
            if ($c === ',' || $c === '"' || $c === "\r" || $c === "\n") {
                return TRUE;
            }
        }
        return FALSE;
    }
}