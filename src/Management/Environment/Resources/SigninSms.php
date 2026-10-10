<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\SmsFactorPolicy;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `signin.sms.*` on the environment plane. */
class SigninSms
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Read the SMS second-factor policy: whether text-message codes are accepted, for which countries, and the administrator rule.
     *
     * `GET /sign-in/sms` · action `signin.sms.get` · scope `signin:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<SmsFactorPolicy>|PendingApprovalResult<ApiResponse<SmsFactorPolicy>> : ApiResponse<SmsFactorPolicy>)
     */
    public function get(?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('signin.sms.get'), [], [], $options, Value::dto(SmsFactorPolicy::fromArray(...)));
    }

    /**
     * Change the SMS second-factor policy: accept text-message codes or not, the countries they may go to, and whether SMS may be an administrator's only factor.
     *
     * `PATCH /sign-in/sms` · action `signin.sms.update` · scope `signin:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{enabled?: bool, allowed_countries?: list<'AC'|'AD'|'AE'|'AF'|'AG'|'AI'|'AL'|'AM'|'AO'|'AR'|'AS'|'AT'|'AU'|'AW'|'AZ'|'BA'|'BB'|'BD'|'BE'|'BF'|'BG'|'BH'|'BI'|'BJ'|'BM'|'BN'|'BO'|'BR'|'BS'|'BT'|'BW'|'BY'|'BZ'|'CA'|'CD'|'CF'|'CG'|'CH'|'CI'|'CK'|'CL'|'CM'|'CN'|'CO'|'CR'|'CU'|'CV'|'CW'|'CY'|'CZ'|'DE'|'DJ'|'DK'|'DM'|'DO'|'DZ'|'EC'|'EE'|'EG'|'ER'|'ES'|'ET'|'FI'|'FJ'|'FK'|'FM'|'FO'|'FR'|'GA'|'GB'|'GD'|'GE'|'GF'|'GH'|'GI'|'GL'|'GM'|'GN'|'GP'|'GQ'|'GR'|'GT'|'GU'|'GW'|'GY'|'HK'|'HN'|'HR'|'HT'|'HU'|'ID'|'IE'|'IL'|'IN'|'IO'|'IQ'|'IR'|'IS'|'IT'|'JM'|'JO'|'JP'|'KE'|'KG'|'KH'|'KI'|'KM'|'KN'|'KP'|'KR'|'KW'|'KY'|'KZ'|'LA'|'LB'|'LC'|'LI'|'LK'|'LR'|'LS'|'LT'|'LU'|'LV'|'LY'|'MA'|'MC'|'MD'|'ME'|'MG'|'MH'|'MK'|'ML'|'MM'|'MN'|'MO'|'MP'|'MQ'|'MR'|'MS'|'MT'|'MU'|'MV'|'MW'|'MX'|'MY'|'MZ'|'NA'|'NC'|'NE'|'NF'|'NG'|'NI'|'NL'|'NO'|'NP'|'NR'|'NU'|'NZ'|'OM'|'PA'|'PE'|'PF'|'PG'|'PH'|'PK'|'PL'|'PM'|'PR'|'PS'|'PT'|'PW'|'PY'|'QA'|'RE'|'RO'|'RS'|'RU'|'RW'|'SA'|'SB'|'SC'|'SD'|'SE'|'SG'|'SH'|'SI'|'SK'|'SL'|'SM'|'SN'|'SO'|'SR'|'SS'|'ST'|'SV'|'SX'|'SY'|'SZ'|'TC'|'TD'|'TG'|'TH'|'TJ'|'TK'|'TL'|'TM'|'TN'|'TO'|'TR'|'TT'|'TV'|'TW'|'TZ'|'UA'|'UG'|'US'|'UY'|'UZ'|'VC'|'VE'|'VG'|'VI'|'VN'|'VU'|'WF'|'WS'|'XK'|'YE'|'ZA'|'ZM'|'ZW'>, privileged_need_stronger_factor?: bool}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<SmsFactorPolicy>|PendingApprovalResult<ApiResponse<SmsFactorPolicy>> : ApiResponse<SmsFactorPolicy>)
     */
    public function update(array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('signin.sms.update'), [], $body, $options, Value::dto(SmsFactorPolicy::fromArray(...)));
    }
}
