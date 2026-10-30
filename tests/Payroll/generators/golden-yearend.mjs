import { writeFileSync } from 'node:fs';
const [src, out] = process.argv.slice(2);
const imp = (m) => import('file:///' + src + '/src/' + m);
const Y = await imp('year-end.js');
const { computePayslip } = await imp('payslip.js');
const { loadCalendar } = await imp('holidays.js');
let seed = 4242;
const rnd = () => ((seed = (seed * 1103515245 + 12345) % 2147483648) / 2147483648);
const pick = (a) => a[Math.floor(rnd() * a.length)];
const money = (lo, hi) => Math.round((lo + rnd() * (hi - lo)) * 100) / 100;
const cases = { thirteenthRun: [], finalPay: [], yearSummary: [] };
const rec = (k, input, fn) => { let output; try { output = fn(); } catch (e) { output = { error: e.message }; } cases[k].push({ input, output }); };
const names = ['Ana Reyes', 'ana reyes', 'Ñora Peña', 'Nora Pena', 'José de la Cruz', 'Jose Dela Cruz', 'O\'Brien, Liam', 'Ma. Teresa Santos', 'Zed Uy', 'Émile Abad', 'Abad Emile', 'Li 2', 'Li 10'];
const person = (i) => {
	const basis = pick(['monthly', 'monthly', 'daily', 'hourly']);
	const e = { id: 'p' + i, name: pick(names) + (rnd() < .3 ? ' ' + i : ''), payBasis: basis, region: pick(['NCR', 'Region VII']), sector: 'non_agri', minimumWageEarner: pick([false, false, 'auto', true]) };
	if (basis === 'monthly') e.monthlySalary = pick([money(12000, 300000), 20000, 60000, 150000]);
	if (basis === 'daily') e.dailyRate = pick([645, 695, money(500, 2500)]);
	if (basis === 'hourly') e.hourlyRate = money(70, 500);
	if (rnd() < .3) e.monthsWorkedInYear = pick([3, 6.5, 11]);
	return e;
};
for (let i = 0; i < 80; i++) {
	const profiles = Array.from({ length: 1 + Math.floor(rnd() * 5) }, (_, k) => person(i * 10 + k));
	const earnedBasic = {}, otherBonuses = {};
	for (const p of profiles) { if (rnd() < .3) earnedBasic[p.id] = pick([0, money(10000, 3000000)]); if (rnd() < .4) otherBonuses[p.id] = pick([0, money(0, 120000), 90000]); }
	rec('thirteenthRun', { profiles, year: pick([2025, 2026]), earnedBasic, otherBonuses }, () => Y.thirteenthMonthRun({ profiles, year: 2026, earnedBasic, otherBonuses }));
}
const causes = ['resignation', 'just_cause', 'redundancy', 'retrenchment', 'closure_not_due_to_losses', 'closure_serious_losses', 'disease', 'labor_saving_device', 'retirement', 'retirement', 'bogus'];
for (let i = 0; i < 400; i++) {
	const employee = person(i);
	const hy = 1985 + Math.floor(rnd() * 41), sy = Math.max(hy, 2026 - Math.floor(rnd() * 2));
	const d = (y) => `${y}-${String(1 + Math.floor(rnd() * 12)).padStart(2, '0')}-${String(1 + Math.floor(rnd() * 31)).padStart(2, '0')}`.replace(/-(02)-(29|3\d)$/, '-$1-28').replace(/-(04|06|09|11)-31$/, '-$1-30');
	const a = { employee, hireDate: d(hy), separationDate: d(sy), cause: pick(causes) };
	if (rnd() < .5) a.unpaidDays = pick([0, 3, 7.5]);
	if (rnd() < .5) a.unusedSilDays = pick([0, 2, 5]);
	if (rnd() < .3) a.earnedBasicYear = money(0, 900000);
	if (rnd() < .3) a.otherBonuses = pick([0, 50000, 95000]);
	if (rnd() < .8) a.birthDate = d(1950 + Math.floor(rnd() * 50));
	if (rnd() < .3) a.firstTimeRetirement = false;
	if (rnd() < .6) { const tc = money(0, 3000000); a.ytd = { taxableCompensation: tc, taxWithheld: Math.round(tc * (0.1 + rnd() * 0.25) * 100) / 100 }; }
	if (rnd() < .2) a.clearance = [{ name: pick(['Cash bond', '  Laptop  ', '']), amount: pick([500, '1200.5', 0, 9e9]) }];
	rec('finalPay', a, () => Y.finalPay(a));
}
const cal = { 2025: loadCalendar(2025), 2026: loadCalendar(2026) };
for (let i = 0; i < 40; i++) {
	const year = pick([2025, 2026]);
	const people = Array.from({ length: 2 + Math.floor(rnd() * 5) }, (_, k) => person(i * 10 + k));
	const slips = [];
	for (const p of people) {
		for (let m = 1; m <= 12; m++) {
			const mo = String(m).padStart(2, '0');
			for (const [s, e] of [['01', '15'], ['16', '28']]) {
				const att = p.payBasis === 'daily' ? { daysWorked: pick([10, 11, 13]) } : p.payBasis === 'hourly' ? { hoursWorked: pick([80, 88]) } : rnd() < .2 ? { absentDays: 1, lateMinutes: 30 } : {};
				const input = { employee: p, period: { frequency: 'SEMI_MONTHLY', start: `${year}-${mo}-${s}`, end: `${year}-${mo}-${e}` }, calendar: cal[year], attendance: att };
				if (rnd() < .2) input.workLogs = [{ date: `${year}-${mo}-${s}`, overtimeHours: 2, regularHours: 8, nightHours: 2, isRestDay: rnd() < .5 }];
				if (rnd() < .3) input.allowances = [{ name: 'Rice', amount: 1000, taxable: false }];
				slips.push(computePayslip(input));
			}
		}
	}
	slips.push(...Y.thirteenthMonthRun({ profiles: people, year }).slips);
	if (rnd() < .5) { try { slips.push(Y.finalPay({ employee: people[0], hireDate: '2015-03-01', separationDate: `${year}-11-30`, cause: pick(['redundancy', 'resignation', 'retirement']), birthDate: '1966-01-01', ytd: { taxableCompensation: 300000, taxWithheld: 20000 } })); } catch {} }
	slips.push({ employeeId: people[1].id, name: people[1].name, period: { start: `${year}-12-31`, end: `${year}-12-31`, frequency: 'MONTHLY' }, earnings: [], deductions: [{ code: 'WITHHOLDING_TAX', label: 'Tax still due (year-end annualization)', amount: 123.45 }] });
	slips.push({ employeeId: people[0].id, name: people[0].name, period: { start: `${year - 1}-12-31`, end: `${year - 1}-12-31`, frequency: 'MONTHLY' }, earnings: [{ code: 'BASIC', amount: 999, taxable: true }], deductions: [] });
	const mwe = people.filter((p) => p.minimumWageEarner === true || rnd() < .15).map((p) => p.id);
	rec('yearSummary', { slips, year, mwe }, () => {
		const rows = Y.annualSummary(slips, year, { mwe: new Set(mwe) }).map((r, k) => (k % 2 ? { ...r, ids: { tin: '123456789', sss: '34-1', philhealth: '', pagibig: 'x,"y"' } } : r));
		return { rows, csv: Y.annualCsv(rows), r2316: rows.map(Y.annual2316Row), csv2316: Y.annual2316Csv(rows), adj: Y.yearEndAdjustmentRun({ rows, year }) };
	});
}
for (const [k, v] of Object.entries(cases)) writeFileSync(`${out}/${k}.json`, JSON.stringify(v));
console.log(Object.fromEntries(Object.entries(cases).map(([k, v]) => [k, `${v.length} cases, ${v.filter((c) => c.output?.error).length} errors`])));
