// Writes golden input -> output cases from the JS engine for the PHP port to reproduce.
import { writeFileSync } from 'node:fs';
const src = process.argv[2], out = process.argv[3];
const imp = (m) => import('file:///' + src + '/src/' + m);
const { sss, philhealth, pagibig, monthlyContributions } = await imp('contributions.js');
const { withholdingTax } = await imp('tax.js');
const { premiumForLog } = await imp('premium.js');
const { loadCalendar } = await imp('holidays.js');
const { thirteenthMonth } = await imp('thirteenth.js');
const { computePayslip } = await imp('payslip.js');
const { minimumWageStatus } = await imp('profile.js');
const { readFileSync } = await import('node:fs');

let seed = 20261030;
const rnd = () => ((seed = (seed * 1103515245 + 12345) % 2147483648) / 2147483648);
const pick = (a) => a[Math.floor(rnd() * a.length)];
const money = (lo, hi) => Math.round((lo + rnd() * (hi - lo)) * 100) / 100;
const cases = {};
const add = (k, input, fn) => { let output; try { output = fn(); } catch (e) { output = { error: e.message }; } (cases[k] ??= []).push({ input, output }); };

for (let i = 0; i < 400; i++) { const m = i < 40 ? i * 2500 : money(0, 200000); add('contributions', { m }, () => ({ sss: sss(m), philhealth: philhealth(m), pagibig: pagibig(m), monthly: monthlyContributions(m) })); }
for (const f of ['DAILY', 'WEEKLY', 'SEMI_MONTHLY', 'MONTHLY', 'ANNUALLY', 'BI_WEEKLY'])
	for (let i = 0; i < 120; i++) { const scale = { DAILY: 40000, WEEKLY: 250000, SEMI_MONTHLY: 500000, MONTHLY: 1000000, ANNUALLY: 12000000, BI_WEEKLY: 1000 }[f]; const x = i < 10 ? i * scale / 100 : money(-100, scale / 10); add('tax', { x, f }, () => withholdingTax(x, f)); }
const cals = { 2025: loadCalendar(2025), 2026: loadCalendar(2026), 2027: loadCalendar(2027), 2031: loadCalendar(2031) };
const hdates = [...new Set(Object.values(cals).flatMap((c) => c.holidays.map((h) => h.date)))];
const anyDate = () => rnd() < 0.5 ? pick(hdates) : `${pick([2025, 2026, 2027])}-${String(1 + Math.floor(rnd() * 12)).padStart(2, '0')}-${String(1 + Math.floor(rnd() * 28)).padStart(2, '0')}`;
const log = () => { const d = anyDate(); const l = { date: d }; if (rnd() < .4) l.isRestDay = true; if (rnd() < .7) l.regularHours = pick([4, 8, 8, 7.5]); if (rnd() < .4) l.nightHours = pick([1, 2, 4, 9]); if (rnd() < .5) l.overtimeHours = pick([1, 2, 3.5]); if (rnd() < .3) l.nightOvertimeHours = pick([1, 2, 5]); return l; };
for (let i = 0; i < 600; i++) { const l = log(); const h = money(50, 1500); add('premium', { log: l, hourly: h, year: Number(l.date.slice(0, 4)) }, () => premiumForLog(l, h, cals[l.date.slice(0, 4)])); }
for (let i = 0; i < 100; i++) { const b = money(0, 2000000), o = rnd() < .5 ? 0 : money(0, 80000); add('thirteenth', { b, o }, () => thirteenthMonth(b, o)); }

const profiles = JSON.parse(readFileSync(src + '/data/profiles.json', 'utf8'));
const regions = ['NCR', 'Region IV-A', 'Region VII', 'BARMM', 'Nowhere'];
const employee = () => {
	if (rnd() < .3) return { ...pick(profiles) };
	const basis = pick(['monthly', 'daily', 'hourly', 'monthly']);
	const e = { id: 'e' + Math.floor(rnd() * 1e6), name: 'Test Person', payBasis: basis, region: pick(regions), sector: pick(['non_agri', 'agri_small', undefined]), minimumWageEarner: pick([true, false, 'auto', 'auto']) };
	if (rnd() < .2) e.dailyRateFactor = pick([313, 365, 261, 262]);
	if (basis === 'monthly') e.monthlySalary = pick([money(8000, 250000), 15000, 30000, 100000]);
	if (basis === 'daily') e.dailyRate = pick([money(400, 3000), 695, 645]);
	if (basis === 'hourly') e.hourlyRate = money(60, 600);
	if (rnd() < .03) e[{ monthly: 'monthlySalary', daily: 'dailyRate', hourly: 'hourlyRate' }[basis]] = 0;
	return e;
};
const period = () => {
	const y = pick([2025, 2026, 2027, 2031]), mo = String(1 + Math.floor(rnd() * 12)).padStart(2, '0');
	const f = pick(['MONTHLY', 'SEMI_MONTHLY', 'SEMI_MONTHLY', 'WEEKLY', 'BI_WEEKLY']);
	if (f === 'MONTHLY') return { frequency: f, start: `${y}-${mo}-01`, end: `${y}-${mo}-28` };
	if (f === 'SEMI_MONTHLY') return rnd() < .5 ? { frequency: f, start: `${y}-${mo}-01`, end: `${y}-${mo}-15` } : { frequency: f, start: `${y}-${mo}-16`, end: `${y}-${mo}-28` };
	const d = 1 + Math.floor(rnd() * 28); const s = new Date(Date.UTC(y, Number(mo) - 1, d)); const e = new Date(s.getTime() + 6 * 864e5);
	return { frequency: f, start: s.toISOString().slice(0, 10), end: e.toISOString().slice(0, 10) };
};
for (let i = 0; i < 1500; i++) {
	const emp = employee(), per = period(), y = Number(per.start.slice(0, 4));
	const input = { employee: emp, period: per };
	const att = {};
	if (rnd() < .5) att.absentDays = pick([0, 1, 2, 0.5]);
	if (rnd() < .5) att.lateMinutes = pick([0, 15, 45, 125]);
	if (rnd() < .3) att.undertimeMinutes = pick([30, 60]);
	if (emp.payBasis === 'daily' && rnd() < .95) att.daysWorked = pick([5, 10, 11, 13, 22, 4.5]);
	if (emp.payBasis === 'hourly' && rnd() < .95) att.hoursWorked = pick([40, 80, 87.5, 160]);
	if (rnd() < .2) att.holidayPayExcludedDates = [pick(hdates)];
	if (Object.keys(att).length) input.attendance = att;
	if (rnd() < .7) input.workLogs = Array.from({ length: 1 + Math.floor(rnd() * 4) }, () => { const l = log(); l.date = per.start.slice(0, 8) + l.date.slice(8); return l; });
	if (rnd() < .4) input.allowances = [{ name: 'Rice subsidy', amount: 1500, taxable: false }, { name: 'Transport', amount: money(100, 3000) }].slice(0, 1 + Math.floor(rnd() * 2));
	if (rnd() < .3) input.loans = [{ loanId: 'L1', name: 'SSS salary loan', amount: money(200, 3000), balance: pick([money(0, 5000), 100, 0]) }];
	if (rnd() < .2) input.otherDeductions = [{ name: 'Cooperative', amount: money(50, 900) }];
	if (rnd() < .3) input.options = { contributionSchedule: pick(['split', 'second_cutoff']) };
	if (emp.minimumWageEarner === 'auto' || emp.minimumWageEarner === undefined) add('mwe', { employee: emp }, () => minimumWageStatus(emp));
	add('payslip', input, () => computePayslip({ ...input, calendar: cals[y] }));
}
for (const [k, v] of Object.entries(cases)) writeFileSync(`${out}/${k}.json`, JSON.stringify(v));
console.log(Object.fromEntries(Object.entries(cases).map(([k, v]) => [k, `${v.length} cases, ${v.filter((c) => c.output?.error).length} errors`])));
