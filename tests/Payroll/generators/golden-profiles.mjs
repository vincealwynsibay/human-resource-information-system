// Golden cases for profile edits, the CSV import and whole-run computation, from the original JS engine.
import { writeFileSync, rmSync } from 'node:fs';
const [src, out, tmp] = process.argv.slice(2);
const imp = (m) => import('file:///' + src + '/src/' + m);
const { updateProfile } = await imp('profile-edit.js');
const { profilesFromCsv } = await imp('profile-import.js');
const { computeRun } = await imp('run-service.js');
const { LoanLedger } = await imp('loans.js');
const { SEED_EMPLOYEES, buildProfiles, seedCsv } = await imp('seed-company.js');
let seed = 5150;
const rnd = () => ((seed = (seed * 1103515245 + 12345) % 2147483648) / 2147483648);
const pick = (a) => a[Math.floor(rnd() * a.length)];
const profiles = buildProfiles(Object.fromEntries(SEED_EMPLOYEES.map((e, i) => [e.email, `emp-${i + 1}`])));
const cases = { profileEdit: [], profileCsv: [], run: [] };
const rec = (k, input, fn) => { let output; try { output = fn(); } catch (e) { output = { error: e.message }; } cases[k].push({ input, output }); };
const val = { payBasis: ['monthly', 'daily', 'hourly', 'weekly', undefined], rate: ['25000', 695, '0', '-5', 'abc', '12,000', ' 800 ', 20000000, 49999.999, undefined],
	hireDate: ['2020-02-29', '2021-02-29', '', null, '2026/01/01', '1999-12-31'], birthDate: ['1990-05-05', '2030-01-01', '', 'x'],
	region: ['NCR', 'Region VII', 'Atlantis', '', null], sector: ['non_agri', 'agri_small', 'farm', ''], minimumWageEarner: ['yes', 'no', 'auto', true, false, '', 'maybe', null],
	lastName: ['Dela Cruz', '', 'O,Neil', 'x'.repeat(31), '  Peña  '], firstName: ['Ana', '"Jo"'], middleName: ['', 'Santos'], nationality: ['FILIPINO', ''],
	regionNumber: ['ncr', ' iv-a ', '0001', '', 'BARMM'], employmentStatus: ['r', 'cp', 'X', ''],
	ids: [{ tin: '123-456-789' }, { tin: '123 456 789 000', sss: '34-1234567-8' }, { philhealth: '12' }, { pagibig: '1234-5678-9012', sss: '' }, {}] };
for (let i = 0; i < 500; i++) {
	const p = { ...pick(profiles) };
	if (rnd() < .2) { delete p.region; p.minimumWageEarner = 'auto'; }
	const patch = {};
	for (const [k, vs] of Object.entries(val)) if (rnd() < .25) patch[k] = pick(vs);
	if (rnd() < .3) patch[pick(['monthlySalary', 'dailyRate', 'hourlyRate'])] = pick([30000, 700, 150]);
	rec('profileEdit', { profile: p, patch }, () => updateProfile(p, patch));
}
const base = seedCsv();
const skeleton = SEED_EMPLOYEES.map((e, i) => ({ id: `emp-${i + 1}`, name: `${e.first.split(' ')[0]} ${e.last}`, monthlySalary: 1 }));
rec('profileCsv', { csv: base, existing: skeleton }, () => profilesFromCsv(base, skeleton));
for (let i = 0; i < 60; i++) {
	const lines = base.split('\n');
	const head = lines[0];
	let csv = pick([
		() => base,
		() => head.split(',').reverse().join(',') + '\n' + lines.slice(1).map((l) => l.split(',').reverse().join(',')).join('\n'),
		() => base.replace(/\n/g, '\r\n'),
		() => lines.map((l, k) => (k && rnd() < .2 ? l.replace(/,\d+(\.\d+)?,/, ',abc,') : l)).join('\n'),
		() => lines.map((l, k) => (k && rnd() < .2 ? 'Nobody Here' + l.slice(l.indexOf(',')) : l)).join('\n'),
		() => 'Employee,Rate\nANA REYES,30000\n"Quoted, Name",1\n\n',
		() => 'id,whatever\nx,1',
		() => 'Name,Salary,TIN,MWE\n' + skeleton.slice(0, 5).map((s) => `${s.name},${pick(['30000', '700'])},${pick(['123-456-789', '1', ''])},${pick(['Yes', 'AUTO', 'nope'])}`).join('\n'),
		() => ''
	])();
	rec('profileCsv', { csv, existing: skeleton }, () => profilesFromCsv(csv, skeleton));
}
for (let i = 0; i < 60; i++) {
	const ps = profiles.filter(() => rnd() < .4);
	const y = pick([2026, 2027, 2031]), mo = String(1 + Math.floor(rnd() * 12)).padStart(2, '0');
	const period = pick([{ frequency: 'MONTHLY', start: `${y}-${mo}-01`, end: `${y}-${mo}-28` }, { frequency: 'SEMI_MONTHLY', start: `${y}-${mo}-16`, end: `${y}-${mo}-28` }, { frequency: 'WEEKLY', start: `${y}-${mo}-22`, end: `${y}-${mo}-28` }]);
	const inputs = {};
	for (const [k, p] of ps.entries()) if (rnd() < .3) inputs[rnd() < .5 ? p.id : `#${k}`] = { attendance: rnd() < .5 ? { daysWorked: 9 } : { lateMinutes: 20 } /* not hoursWorked: null, which JS reads as 0 hours and the PHP port treats as not given */, allowances: [{ name: 'Rice', amount: 500, taxable: false }] };
	const defaults = pick([{ daysWorked: 22, hoursWorked: 96 }, { daysWorked: '11', hoursWorked: '' }, undefined]);
	const schedule = pick(['split', 'second_cutoff', undefined]);
	const loans = rnd() < .5 && ps.length ? [{ employeeId: ps[0].id, name: 'SSS loan', principal: 5000, amortization: 1500 }, { employeeId: ps[ps.length - 1].id, name: 'Cash advance', principal: 900, amortization: 1000 > 900 ? 900 : 1000 }] : [];
	const file = `${tmp}/run-ledger.json`; rmSync(file, { force: true });
	const ledger = new LoanLedger(file); const ids = loans.map((l) => ledger.add(l).id);
	const input = { profiles: ps, period, inputs, defaults, schedule, loans };
	rec('run', input, () => JSON.parse(JSON.stringify(computeRun({ profiles: ps, period, inputs, defaults, schedule, ledger: loans.length ? ledger : undefined }), (k, v) => (typeof v === 'string' && ids.includes(v) ? 'L' + (ids.indexOf(v) + 1) : v))));
	rmSync(file, { force: true });
}
for (const [k, v] of Object.entries(cases)) writeFileSync(`${out}/${k}.json`, JSON.stringify(v));
console.log(Object.fromEntries(Object.entries(cases).map(([k, v]) => [k, `${v.length} cases, ${v.filter((c) => c.output?.error).length} errors`])));
