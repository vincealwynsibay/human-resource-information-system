# Data attribution

The JSON tables in this folder are copied unmodified from
https://github.com/open-payroll-data/philippines-payroll-data (MIT, see DATASET-LICENSE.txt).
Each file carries its own `source` block citing the primary government issuance
(SSS Circular 2024-006, PhilHealth, Pag-IBIG HDMF, BIR TRAIN / RR 11-2018, DOLE).
`examples.json` is used as a test fixture.

`min_wage_2025.json` is volatile: each regional wage board issues orders year-round. Its `matrix_as_of` date and
NWPC source URL are inside the file. Re-pull the NWPC matrix before relying on a rate.

`withholding_annex_e.json` is not from that dataset: it is transcribed from the BIR's own Annex E of Revenue Regulations
No. 11-2018 (source URL inside the file) and replaces the dataset's monthly and semi-monthly tables, whose fixed taxes
are rounded to whole pesos (8,542 where the BIR prints 8,541.80).
