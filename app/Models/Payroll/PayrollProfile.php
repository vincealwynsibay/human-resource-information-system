<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;

/** An employee's pay profile, in the shape the payroll engine reads (see App\Payroll\Profile). */
class PayrollProfile extends Model
{
    protected $fillable = ['employee_id', 'name', 'data'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    /** The profile as the engine expects it: its id is the employee id. */
    public function toEngine(): array
    {
        return ['id' => $this->employee_id, 'name' => $this->name, ...$this->data];
    }
}
