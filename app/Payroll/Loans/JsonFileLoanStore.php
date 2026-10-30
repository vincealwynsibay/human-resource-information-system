<?php

namespace App\Payroll\Loans;

class JsonFileLoanStore implements LoanStore
{
    public function __construct(private string $file)
    {
    }

    public function read(): array
    {
        return is_file($this->file) ? json_decode(file_get_contents($this->file), true, flags: JSON_THROW_ON_ERROR) : [];
    }

    public function write(array $loans): void
    {
        if (!is_dir(dirname($this->file))) {
            mkdir(dirname($this->file), 0777, true);
        }
        file_put_contents($this->file, json_encode($loans, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }
}
