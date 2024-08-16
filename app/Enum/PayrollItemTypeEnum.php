<?php

enum PayrollItemTypeEnum: string
{

    case SALARY = 'SALARY';
    case OVERTIME = 'OVERTIME';
    case BONUS = 'BONUS';
    case DEDUCTION = 'DEDUCTION';
    case ALLOWANCE = 'ALLOWANCE';
    case COMMISSION = 'COMMISSION';
    case LEAVE = 'LEAVE';
    case HOLIDAY = 'HOLIDAY';
    // case LOAN = 'LOAN';
}