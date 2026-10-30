<?php

namespace App\Payroll\Loans;

use RuntimeException;

/** A refused loan operation; the code is the HTTP status to answer with. */
class LoanException extends RuntimeException
{
}
