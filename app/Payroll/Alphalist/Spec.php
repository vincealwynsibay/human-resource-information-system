<?php

namespace App\Payroll\Alphalist;

/**
 * File structure of the BIR annual alphalist of employees (BIR Form 1604-C), from Annex "A" of Revenue Memorandum
 * Circular No. 25-2024 (pages 6 to 9, "File structure for annual alphalist of payees, attachment to BIR Form No. 1604C"):
 *   https://bir-cdn.bir.gov.ph/BIR/pdf/RMC%20No.%2025-2024%20Annex%20A.pdf
 * and the file name rule from Annex "B" of RMC No. 15-2025:
 *   https://bir-cdn.bir.gov.ph/BIR/pdf/RMC%20No.%2015-2025%20Annex%20B.pdf
 * The annex lists fields in order with TEXT / NUMBER / DATE types and widths. It does not state how a line is
 * delimited or quoted; see Builder for how that was settled against the BIR's own validation module.
 *
 * A field is [name, type, width]; type T = TEXT, N = NUMBER 9(11).99, D = DATE MM/DD/YYYY, I = NUMBER integer.
 */
class Spec
{
    public const FORM_TYPE = '1604C';
    public const HEADER_CODE = 'H1604C';

    /** Lists of values from the annex. */
    public const LOV = [
        'EMPLOYMENT_STATUS' => ['R' => 'Regular', 'C' => 'Casual', 'CP' => 'Contractual/Project-Based', 'S' => 'Seasonal', 'P' => 'Probationary', 'AL' => 'Apprentice/Learners'],
        'REASON_SEPARATION' => ['NA' => 'N/A', 'T' => 'Terminated/Resigned', 'TR' => 'Transferred', 'R' => 'Retirement', 'D' => 'Death'],
        'SUBS_FILING' => ['Y' => 'Yes', 'N' => 'No'],
    ];

    /**
     * REGION_NUM is TEXT, not a zero-padded number: the Alphalist 7.4 validator rejects "0001" and accepts these codes
     * (NCR, CAR, ARMM, Roman identifiers, and unpadded numbers).
     */
    public const REGION_CODES = [
        'I', 'II', 'III', 'IV-A', 'IV-B', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII', 'XIII', 'NCR', 'ARMM', 'CAR',
        '1', '2', '3', '4-A', '4-B', '5', '6', '7', '8', '9', '10', '11', '12', '13',
    ];

    /** Region names to the validator's region codes. BARMM is not on the validator's list: ARMM is the closest. */
    public const REGION_NAME_TO_CODE = [
        'NCR' => 'NCR', 'CAR' => 'CAR', 'Region I' => 'I', 'Region II' => 'II', 'Region III' => 'III', 'Region IV-A' => 'IV-A', 'Region IV-B' => 'IV-B', 'Region V' => 'V',
        'Region VI' => 'VI', 'Region VII' => 'VII', 'Region VIII' => 'VIII', 'Region IX' => 'IX', 'Region X' => 'X', 'Region XI' => 'XI', 'Region XII' => 'XII', 'Region XIII' => 'XIII', 'BARMM' => 'ARMM',
    ];

    /** Text and date fields written without quotes, by record kind. Everything else (numbers aside) is double-quoted. */
    public const BARE = [
        'header' => ['FTYPE_CODE', 'RETRN_PERIOD'],
        'detail' => ['SCHEDULE_NUM'],
        'control' => ['SCHEDULE_NUM', 'RETRN_PERIOD'],
    ];

    /** In Schedule 1 the annex names one control total slightly differently from the detail column it sums. */
    public const CONTROL_ALIAS = ['PRES_TAXABLE_SALARIES' => 'PRES_TAXABLES_SALARIES'];

    public static function header(): array
    {
        return [['FTYPE_CODE', 'T', 6], ['TIN', 'T', 9], ['BRANCH_CODE', 'T', 4], ['RETRN_PERIOD', 'D', 10]];
    }

    private static function lead(): array
    {
        return [['SCHEDULE_NUM', 'T', 2], ['FTYPE_CODE', 'T', 5], ['TIN_EMPYR', 'T', 9], ['BRANCH_CODE_EMPLYR', 'T', 4], ['RETRN_PERIOD', 'D', 10]];
    }

    private static function controlLead(): array
    {
        // sic: the annex spells TIM_EMPYR in the control records
        return [['SCHEDULE_NUM', 'T', 2], ['FTYPE_CODE', 'T', 5], ['TIM_EMPYR', 'T', 9], ['BRANCH_CODE_EMPLYR', 'T', 4], ['RETRN_PERIOD', 'D', 10]];
    }

    private static function who(): array
    {
        return [['SEQ_NUM', 'I', 6], ['TIN', 'T', 9], ['BRANCH_CODE', 'T', 4], ['LAST_NAME', 'T', 30], ['FIRST_NAME', 'T', 30], ['MIDDLE_NAME', 'T', 30], ['REGION_NUM', 'T', 4]];
    }

    private static function money(string ...$names): array
    {
        return array_map(fn ($n) => [$n, 'N', 14], $names);
    }

    private static function tail(): array
    {
        return [['NATIONALITY', 'T', 30], ['EMPLOYMENT_STATUS', 'T', 2], ['REASON_SEPARATION', 'T', 2], ['SUBS_FILING', 'T', 2]];
    }

    /** Schedule 1: employees who are not minimum-wage earners. 49 detail fields. */
    public static function schedule1Detail(): array
    {
        return [
            ...self::lead(), ...self::who(),
            ...self::money('PREV_NONTAX_GROSS_COMP_INCOME', 'PREV_NONTAX_BASIC_SMW', 'PREV_NONTAX_13TH_MONTH', 'PREV_NONTAX_DE_MINIMIS', 'PREV_NONTAX_SSS_ETC', 'PREV_NONTAX_SALARIES',
                'PREV_TOTAL_NONTAX_COMP_INCOME', 'PREV_TAXABLE_BASIC_SALARY', 'PREV_TAXABLE_13TH_MONTH', 'PREV_TAXABLE_SALARIES', 'PREV_TOTAL_TAXABLE'),
            ['EMPLOYMENT_FROM', 'D', 10], ['EMPLOYMENT_TO', 'D', 10],
            ...self::money('PRES_NONTAX_GROSS_COMP_INCOME', 'NONTAX_BASIC_SAL', 'PRES_NONTAX_13TH_MONTH', 'PRES_NONTAX_DE_MINIMIS', 'PRES_NONTAX_SSS_ETC', 'PRES_NONTAX_SALARIES',
                'PRES_TOTAL_NONTAX_COMP_INCOME', 'TAX_BASIC_SAL', 'PRES_TAXABLE_13TH_MONTH', 'PRES_TAXABLES_SALARIES', 'PRES_TOTAL_COMP', 'GROSS_COMP_INCOME',
                'NET_TAXABLE_COMP_INCOME', 'TAX_DUE', 'PREV_TAX_WTHLD', 'PRES_TAX_WTHLD', 'AMT_WTHLD_DEC', 'OVER_WTHLD', 'ACTUAL_AMT_WTHLD'),
            ...self::tail(),
            ...self::money('TAX_CREDIT_PERA'),
        ];
    }

    /** Schedule 1 control record: totals of the detail columns. 36 fields. */
    public static function schedule1Control(): array
    {
        return [
            ...self::controlLead(),
            ...self::money('PREV_NONTAX_GROSS_COMP_INCOME', 'PREV_NONTAX_BASIC_SMW', 'PREV_NONTAX_13TH_MONTH', 'PREV_NONTAX_DE_MINIMIS', 'PREV_NONTAX_SSS_ETC', 'PREV_NONTAX_SALARIES',
                'PREV_TOTAL_NONTAX_COMP_INCOME', 'PREV_TAXABLE_BASIC_SALARY', 'PREV_TAXABLE_13TH_MONTH', 'PREV_TAXABLE_SALARIES', 'PREV_TOTAL_TAXABLE',
                'PRES_NONTAX_GROSS_COMP_INCOME', 'NONTAX_BASIC_SAL', 'PRES_NONTAX_13TH_MONTH', 'PRES_NONTAX_DE_MINIMIS', 'PRES_NONTAX_SSS_ETC', 'PRES_NONTAX_SALARIES',
                'PRES_TOTAL_NONTAX_COMP_INCOME', 'TAX_BASIC_SAL', 'PRES_TAXABLE_13TH_MONTH', 'PRES_TAXABLE_SALARIES', 'PRES_TOTAL_COMP', 'GROSS_COMP_INCOME',
                'NET_TAXABLE_COMP_INCOME', 'TAX_DUE', 'PREV_TAX_WTHLD', 'PRES_TAX_WTHLD', 'AMT_WTHLD_DEC', 'OVER_WTHLD', 'ACTUAL_AMT_WTHLD', 'TAX_CREDIT_PERA'),
        ];
    }

    /** Schedule 2: minimum-wage earners. 59 detail fields. */
    public static function schedule2Detail(): array
    {
        return [
            ...self::lead(), ...self::who(),
            ...self::money('PREV_NONTAX_GROSS_COMP_INCOME', 'PREV_NONTAX_BASIC_SMW', 'PREV_NONTAX_HOLIDAY_PAY', 'PREV_NONTAX_OVERTIME_PAY', 'PREV_NONTAX_NIGHT_DIFF', 'PREV_NONTAX_HAZARD_PAY',
                'PREV_NONTAX_13TH_MONTH', 'PREV_NONTAX_DE_MINIMIS', 'PREV_NONTAX_SSS_ETC', 'PREV_NONTAX_SALARIES', 'PREV_TOTAL_NONTAX_COMP_INCOME', 'PREV_TAXABLE_13TH_MONTH',
                'PREV_TAXABLE_SALARIES', 'PREV_TOTAL_TAXABLE'),
            ['EMPLOYMENT_FROM', 'D', 10], ['EMPLOYMENT_TO', 'D', 10],
            ...self::money('PRES_NONTAX_GROSS_COMP_INCOME', 'PRES_NONTAX_BASIC_SMW_DAY', 'PRES_NONTAX_BASIC_SMW_MONTH', 'PRES_NONTAX_BASIC_SMW_YEAR'),
            ['FACTOR_USED', 'I', 3],
            ...self::money('PRES_NONTAX_HOLIDAY_PAY', 'PRES_NONTAX_OVERTIME_PAY', 'PRES_NONTAX_NIGHT_DIFF', 'PRES_NONTAX_HAZARD_PAY', 'PRES_NONTAX_13TH_MONTH', 'PRES_NONTAX_DE_MINIMIS',
                'PRES_NONTAX_SSS_ETC', 'PRES_NONTAX_SALARIES', 'PRES_TOTAL_NONTAX_COMP_INCOME', 'PRES_TAXABLE_13TH_MONTH', 'PRES_TAXABLE_SALARIES', 'PRES_TOTAL_COMP', 'GROSS_COMP_INCOME',
                'NET_TAXABLE_COMP_INCOME', 'TAX_DUE', 'PREV_TAX_WTHLD', 'PRES_TAX_WTHLD', 'AMT_WTHLD_DEC', 'OVER_WTHLD', 'ACTUAL_AMT_WTHLD'),
            ...self::tail(),
            ...self::money('TAX_CREDIT_PERA', 'NONTAX_BASIC_SAL'),
        ];
    }

    /** Schedule 2 control record. 45 fields. */
    public static function schedule2Control(): array
    {
        return [
            ...self::controlLead(),
            ...self::money('PREV_NONTAX_GROSS_COMP_INCOME', 'PREV_NONTAX_BASIC_SMW', 'PREV_NONTAX_HOLIDAY_PAY', 'PREV_NONTAX_OVERTIME_PAY', 'PREV_NONTAX_NIGHT_DIFF', 'PREV_NONTAX_HAZARD_PAY',
                'PREV_NONTAX_13TH_MONTH', 'PREV_NONTAX_DE_MINIMIS', 'PREV_NONTAX_SSS_ETC', 'PREV_NONTAX_SALARIES', 'PREV_TOTAL_NONTAX_COMP_INCOME', 'PREV_TAXABLE_13TH_MONTH',
                'PREV_TAXABLE_SALARIES', 'PREV_TOTAL_TAXABLE', 'PRES_NONTAX_GROSS_COMP_INCOME', 'PRES_NONTAX_BASIC_SMW_DAY', 'PRES_NONTAX_BASIC_SMW_MONTH', 'PRES_NONTAX_BASIC_SMW_YEAR',
                'PRES_NONTAX_HOLIDAY_PAY', 'PRES_NONTAX_OVERTIME_PAY', 'PRES_NONTAX_NIGHT_DIFF', 'PRES_NONTAX_HAZARD_PAY', 'PRES_NONTAX_13TH_MONTH', 'PRES_NONTAX_DE_MINIMIS',
                'PRES_NONTAX_SSS_ETC', 'PRES_NONTAX_SALARIES', 'PRES_TOTAL_NONTAX_COMP_INCOME', 'PRES_TAXABLE_13TH_MONTH', 'PRES_TAXABLE_SALARIES', 'PRES_TOTAL_COMP', 'GROSS_COMP_INCOME',
                'NET_TAXABLE_COMP_INCOME', 'TAX_DUE', 'PREV_TAX_WTHLD', 'PRES_TAX_WTHLD', 'AMT_WTHLD_DEC', 'OVER_WTHLD', 'ACTUAL_AMT_WTHLD', 'TAX_CREDIT_PERA', 'NONTAX_BASIC_SAL'),
        ];
    }

    /** The detail column a control total sums: the same name, or the annex alias when the schedule has no such column. */
    public static function detailColumnFor(string $controlName, array $detailFields): string
    {
        foreach ($detailFields as [$n]) {
            if ($n === $controlName) {
                return $controlName;
            }
        }
        return self::CONTROL_ALIAS[$controlName] ?? $controlName;
    }

    /** Annex B file name: <TIN><BC><RETURN PERIOD MMDDYYYY><FORM TYPE>.DAT */
    public static function fileName(string $tin, string $branch, string $mmddyyyy): string
    {
        return "{$tin}{$branch}{$mmddyyyy}" . self::FORM_TYPE . '.DAT';
    }

    public static function isRealDate(mixed $v): bool
    {
        if (!is_string($v) || !preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $v)) {
            return false;
        }
        [$m, $d, $y] = array_map('intval', explode('/', $v));
        return checkdate($m, $d, $y);
    }
}
