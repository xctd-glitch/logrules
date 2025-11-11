<?php

declare(strict_types=1);

namespace SRP\Validation;

final class CountryCodeValidator
{
    /**
     * @var array<int, string>
     */
    private const ISO_ALPHA2 = [
        'AD',
        'AE',
        'AF',
        'AG',
        'AI',
        'AL',
        'AM',
        'AO',
        'AQ',
        'AR',
        'AS',
        'AT',
        'AU',
        'AW',
        'AX',
        'AZ',
        'BA',
        'BB',
        'BD',
        'BE',
        'BF',
        'BG',
        'BH',
        'BI',
        'BJ',
        'BL',
        'BM',
        'BN',
        'BO',
        'BQ',
        'BR',
        'BS',
        'BT',
        'BV',
        'BW',
        'BY',
        'BZ',
        'CA',
        'CC',
        'CD',
        'CF',
        'CG',
        'CH',
        'CI',
        'CK',
        'CL',
        'CM',
        'CN',
        'CO',
        'CR',
        'CU',
        'CV',
        'CW',
        'CX',
        'CY',
        'CZ',
        'DE',
        'DJ',
        'DK',
        'DM',
        'DO',
        'DZ',
        'EC',
        'EE',
        'EG',
        'EH',
        'ER',
        'ES',
        'ET',
        'FI',
        'FJ',
        'FK',
        'FM',
        'FO',
        'FR',
        'GA',
        'GB',
        'GD',
        'GE',
        'GF',
        'GG',
        'GH',
        'GI',
        'GL',
        'GM',
        'GN',
        'GP',
        'GQ',
        'GR',
        'GS',
        'GT',
        'GU',
        'GW',
        'GY',
        'HK',
        'HM',
        'HN',
        'HR',
        'HT',
        'HU',
        'ID',
        'IE',
        'IL',
        'IM',
        'IN',
        'IO',
        'IQ',
        'IR',
        'IS',
        'IT',
        'JE',
        'JM',
        'JO',
        'JP',
        'KE',
        'KG',
        'KH',
        'KI',
        'KM',
        'KN',
        'KP',
        'KR',
        'KW',
        'KY',
        'KZ',
        'LA',
        'LB',
        'LC',
        'LI',
        'LK',
        'LR',
        'LS',
        'LT',
        'LU',
        'LV',
        'LY',
        'MA',
        'MC',
        'MD',
        'ME',
        'MF',
        'MG',
        'MH',
        'MK',
        'ML',
        'MM',
        'MN',
        'MO',
        'MP',
        'MQ',
        'MR',
        'MS',
        'MT',
        'MU',
        'MV',
        'MW',
        'MX',
        'MY',
        'MZ',
        'NA',
        'NC',
        'NE',
        'NF',
        'NG',
        'NI',
        'NL',
        'NO',
        'NP',
        'NR',
        'NU',
        'NZ',
        'OM',
        'PA',
        'PE',
        'PF',
        'PG',
        'PH',
        'PK',
        'PL',
        'PM',
        'PN',
        'PR',
        'PS',
        'PT',
        'PW',
        'PY',
        'QA',
        'RE',
        'RO',
        'RS',
        'RU',
        'RW',
        'SA',
        'SB',
        'SC',
        'SD',
        'SE',
        'SG',
        'SH',
        'SI',
        'SJ',
        'SK',
        'SL',
        'SM',
        'SN',
        'SO',
        'SR',
        'SS',
        'ST',
        'SV',
        'SX',
        'SY',
        'SZ',
        'TC',
        'TD',
        'TF',
        'TG',
        'TH',
        'TJ',
        'TK',
        'TL',
        'TM',
        'TN',
        'TO',
        'TR',
        'TT',
        'TV',
        'TW',
        'TZ',
        'UA',
        'UG',
        'UM',
        'US',
        'UY',
        'UZ',
        'VA',
        'VC',
        'VE',
        'VG',
        'VI',
        'VN',
        'VU',
        'WF',
        'WS',
        'YE',
        'YT',
        'ZA',
        'ZM',
        'ZW',
    ];

    /**
     * @var array<string, true>|null
     */
    private static ?array $index = null;

    /**
     * @return array<string, true>
     */
    private static function index(): array
    {
        if (self::$index === null) {
            self::$index = array_fill_keys(self::ISO_ALPHA2, true);
        }

        return self::$index;
    }

    public static function normalize(string $code): string
    {
        $normalized = strtoupper(trim($code));
        if ($normalized === '' || strlen($normalized) !== 2) {
            return '';
        }

        if (!ctype_alpha($normalized)) {
            return '';
        }

        return $normalized;
    }

    public static function isValid(string $code): bool
    {
        $normalized = self::normalize($code);
        if ($normalized === '') {
            return false;
        }

        return isset(self::index()[$normalized]);
    }

    /**
     * @param array<int, string>|string $input
     * @return list<string>
     */
    public static function sanitizeList(array|string $input): array
    {
        $parts = [];
        if (is_string($input)) {
            $normalizedInput = str_replace(["\r\n", "\r", "\n", ';'], ',', $input);
            $split = preg_split('~\s*,\s*~', $normalizedInput, -1, PREG_SPLIT_NO_EMPTY);
            $parts = $split !== false ? $split : [];
        } elseif (is_array($input)) {
            $parts = $input;
        }

        $valid = [];
        foreach ($parts as $part) {
            if (!is_string($part)) {
                continue;
            }

            $candidate = self::normalize($part);
            if ($candidate === '' || !isset(self::index()[$candidate])) {
                continue;
            }

            $valid[$candidate] = true;
        }

        return array_keys($valid);
    }

    public static function ensureOrFallback(string $code, string $fallback = 'XX'): string
    {
        $normalized = self::normalize($code);
        if ($normalized === '' || !isset(self::index()[$normalized])) {
            $fallbackNormalized = self::normalize($fallback);
            if ($fallbackNormalized !== '' && isset(self::index()[$fallbackNormalized])) {
                return $fallbackNormalized;
            }

            return $fallbackNormalized !== '' ? $fallbackNormalized : 'XX';
        }

        return $normalized;
    }
}
