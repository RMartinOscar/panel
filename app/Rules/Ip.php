<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Ip implements ValidationRule
{
    public const IPV4_REGEX = '/^(?<x>[0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.(?&x)\.(?&x)\.(?&x)(\/([0-9]|[12][0-9]|3[0-2]))?$/';
    public const IPV6_REGEX = '/^(?<y>[0-9a-f]{1,4})(::?(?&y)){1,7}(\/([0-9]|[1-9][0-9]|1[01][0-9]|12[0-8]))?$/';

    // public const IP_REGEX = '/(' . self::IPV4_REGEX . ')|(' . self::IPV6_REGEX . ')/';

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $msg = 'The :attribute field must be a valid IP Address';
        if (str($value)->contains(':') && !preg_match(self::IPV6_REGEX, $value)) {
            $fail($msg . '6');
            return;
        }
        if (!preg_match(self::IPV4_REGEX, $value)) {
            $fail($msg . '4');
        }

        /* if ((str($value)->contains(':') && !preg_match(self::IPV6_REGEX, $value)) || !preg_match(self::IPV4_REGEX, $value)) {
            $fail('The :attribute field must be a valid IP Address');
        } */
    }
}
