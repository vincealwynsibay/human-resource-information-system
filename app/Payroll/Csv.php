<?php

namespace App\Payroll;

class Csv
{
    /** Minimal RFC 4180 reader: quoted fields, doubled quotes, CRLF or LF. Rows that are all blank are dropped. */
    public static function parse(string $text): array
    {
        $rows = [];
        $row = [];
        $field = '';
        $quoted = false;
        $chars = mb_str_split($text);
        $count = count($chars);
        $keep = function (array $row) use (&$rows) {
            foreach ($row as $v) {
                if (trim($v) !== '') {
                    $rows[] = $row;
                    return;
                }
            }
        };
        for ($i = 0; $i < $count; $i++) {
            $c = $chars[$i];
            if ($quoted) {
                if ($c === '"' && ($chars[$i + 1] ?? null) === '"') {
                    $field .= '"';
                    $i++;
                } elseif ($c === '"') {
                    $quoted = false;
                } else {
                    $field .= $c;
                }
            } elseif ($c === '"') {
                $quoted = true;
            } elseif ($c === ',') {
                $row[] = $field;
                $field = '';
            } elseif ($c === "\n" || $c === "\r") {
                if ($c === "\r" && ($chars[$i + 1] ?? null) === "\n") {
                    $i++;
                }
                $row[] = $field;
                $field = '';
                $keep($row);
                $row = [];
            } else {
                $field .= $c;
            }
        }
        $row[] = $field;
        $keep($row);
        return $rows;
    }
}
