# Talia

HR and payroll for Philippine companies: people, leave, overtime and time records, and payroll that follows Philippine
law down to the centavo, from the payslip to the government filings.

[![tests](https://github.com/vincealwynsibay/human-resource-information-system/actions/workflows/tests.yml/badge.svg)](https://github.com/vincealwynsibay/human-resource-information-system/actions/workflows/tests.yml)

## What it does

**For HR**: employees with their job, department, supervisor and schedule; leave entitlements and requests; overtime
requests; clock-in and clock-out time records; a dashboard of headcount, today's attendance and what is waiting for a
decision.

**For payroll**:

- Semi-monthly, monthly or weekly runs computed from the time records: absences, late and undertime, approved
  overtime, rest-day and holiday premiums from the DOLE rules.
- SSS (with EC and the MPF part), PhilHealth and Pag-IBIG contributions, and BIR withholding tax from the
  Revenue Regulations 11-2018 tables. Minimum-wage earners are recognised from the regional wage orders and exempted.
- Loans deducted each period, never beyond their balance.
- Maker-checker approval: the person who prepares a run cannot approve it.
- 13th-month pay, final pay on separation, and the year-end tax adjustment.
- What goes out afterwards: payslip PDFs, a bank credit file, the BIR 1601-C worksheet, the SSS, PhilHealth and
  Pag-IBIG contribution lists, Form 2316 figures and the BIR 1604-C alphalist file.

**For employees**: their payslips (and PDFs), time records, leave and overtime requests.

## How the payroll is proven

Payroll mistakes cost people money, so the figures are checked against things that are known to be right:

- **5,462 recorded cases.** The engine began as a JavaScript project. Its results for contributions, tax, premiums,
  minimum-wage status, payslips, loans, 13th month, final pay and the alphalist were recorded and the PHP engine has to
  reproduce every one of them (`php tests/Payroll/run-golden.php`).
- **Files the BIR accepted.** Two 1604-C alphalist files that the BIR accepted are rebuilt from their inputs and
  compared byte for byte.
- **One summary behind every report.** Form 2316, the alphalist and the monthly 1601-C are all built from the same
  per-employee summary of the payslips, so the twelve monthly returns add up to the year-end ones. The contribution
  lists are worked back from what the payslips actually deducted, so they cannot disagree with them.
- **Sources in the data.** Each rate table carries the circular or regulation it comes from
  ([resources/payroll](resources/payroll/ATTRIBUTION.md)); the BIR withholding tables are transcribed from Annex E of
  RR 11-2018 itself, because a public dataset had rounded them to whole pesos.

The test suites run on every push: the payroll engine on SQLite, the HR API against MySQL 8 with the real schema, and
the front-end build.

## What changed in the original HR code

The HR side started in 2024 as a Laravel API on MySQL stored procedures ([how it works](docs/hr-processes.md)). Bringing
it back to life turned up real problems, now fixed and covered by regression tests:

- **Employee restrictions were never enforced.** The role checks read `$user->roleID`, but the column is `RoleID`, so
  every employee-only restriction was skipped. Ownership checks also compared records with the login id instead of the employee id.
- **Employees could act on other people's requests**: file leave and overtime for someone else, and edit or delete any
  request. Requests now belong to their employee, and only pending ones can change.
- **Six stored procedures the controllers call were missing** from the schema, and several list procedures had
  inverted filters and a constant sort.

The HR side is moving from stored procedures to Eloquent one area at a time, with those tests as the safety net.

## How it is built

- **Laravel 12** API with Sanctum session authentication.
- **Vue 3 with Inertia** and Tailwind CSS 4 for the app.
- **The payroll engine** (`app/Payroll`) is plain PHP working on arrays: it does not touch the database or the
  framework, which is what lets the recorded cases run against it directly. `PayrollService` connects it to the
  database: runs, approvals, loans and year-end.
- **Filings** (`app/Payroll/Filings`, `app/Payroll/Alphalist`) build the government reports from saved payslips; bank
  file layouts plug in through `config/payroll.php` (`app/Payroll/Bank`).
- **PDFs** are rendered from Blade templates by headless Chromium (spatie/laravel-pdf).

## Running it

You need PHP 8.4, Composer, Node 22 and Docker (for MySQL).

```sh
composer install
npm install && npx vite build
cp .env.example .env && php artisan key:generate

docker run -d --name hris-mysql -e MYSQL_ROOT_PASSWORD=secret -e MYSQL_DATABASE=hris -p 33306:3306 mysql:8.0
# in .env: DB_CONNECTION=mysql, DB_PORT=33306, DB_DATABASE=hris, DB_USERNAME=root, DB_PASSWORD=secret

php artisan hris:demo --fresh   # the schema, then a fictional company with 22 months of history
php artisan serve
```

Sign in as `admin` (prepares payroll), `approver` (approves it) or `employee`, password `demo1234`. Every name and
number in the demo company is invented.

Loading the schema uses the `mysql` command-line client, so it needs to be on your PATH.

### Tests

```sh
php vendor/bin/pest --testsuite Payroll    # the payroll engine and API, on SQLite
php tests/Payroll/run-golden.php           # the recorded cases and the BIR-accepted files
php vendor/bin/pest --testsuite Feature    # the HR API, on the MySQL database in .env.testing
```

### Deploying

[deploy/](deploy/README.md) runs the public demo on one small server with Docker: the app, MySQL, a nightly rebuild of
the demo and automatic HTTPS.

## Accuracy

The rates follow the issuances cited in each table, as of the dates they carry. Minimum wages change whenever a regional
board issues a new wage order, and contribution tables are reviewed each January; check them before relying on a figure.
The filings are worksheets for entering in the agencies' own systems, not the official forms.
