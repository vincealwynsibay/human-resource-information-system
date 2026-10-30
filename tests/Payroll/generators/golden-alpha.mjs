import { writeFileSync, readFileSync, mkdirSync } from 'node:fs';
const [src, out, accepted] = process.argv.slice(2);
const imp = (m) => import('file:///' + src + '/src/' + m);
const { runDemoYear, DEMO_PROFILES, EMPLOYER } = await imp('demo-year.js');
const { SEED_EMPLOYEES, buildProfiles } = await imp('seed-company.js');
const { computeRun } = await imp('run-service.js');
const Y = await imp('year-end.js');
const { buildAlphalist } = await imp('alphalist.js');
const { checkAlphalistDat } = await imp('alphalist-check.js');
const { minimumWageStatus } = await imp('profile.js');

// 1. the two files the official BIR module accepted, rebuilt from their inputs
const demo = runDemoYear();
const fakeIds = Object.fromEntries(SEED_EMPLOYEES.map((e, i) => [e.email, `emp-${i + 1}`]));
const profiles = buildProfiles(fakeIds);
const slips = [];
for (let m = 1; m <= 12; m++) {
	const mm = String(m).padStart(2, '0'), end = new Date(Date.UTC(2026, m, 0)).getUTCDate();
	slips.push(...computeRun({ profiles, period: { start: `2026-${mm}-01`, end: `2026-${mm}-${end}`, frequency: 'MONTHLY' }, defaults: { daysWorked: 22, hoursWorked: 96 } }).slips);
}
slips.push(...Y.thirteenthMonthRun({ profiles, year: 2026 }).slips);
const mwe = new Set(profiles.filter((p) => minimumWageStatus(p).earner).map((p) => p.id));
slips.push(...Y.yearEndAdjustmentRun({ rows: Y.annualSummary(slips, 2026, { mwe }), year: 2026 }).slips);
const seedRows = Y.annualSummary(slips, 2026, { mwe }).map((r) => ({ ...r, ids: profiles.find((p) => p.id === r.employeeId).ids }));
const sets = {
	demo: { rows: demo.rows, profiles: DEMO_PROFILES, year: 2026, employer: EMPLOYER, file: src + '/demo-out/SYNTHETIC-9000000000000123120261604C.DAT' },
	seed: { rows: seedRows, profiles, year: 2026, employer: { tin: '900000000', branch: '0000' }, file: accepted }
};
mkdirSync(out + '/bir-accepted', { recursive: true });
for (const [name, s] of Object.entries(sets)) {
	const built = buildAlphalist(s);
	const disk = readFileSync(s.file, 'latin1');
	console.log(name, 'errors', built.errors.length, 'JS rebuild identical to the accepted file:', built.content === disk, built.fileName);
	writeFileSync(`${out}/bir-accepted/${name}-${built.fileName}`, disk, 'latin1');
	writeFileSync(`${out}/bir-accepted/${name}-input.json`, JSON.stringify({ rows: s.rows, profiles: s.profiles, year: s.year, employer: s.employer }));
}

// 2. variations: problems, edge cases, the checker on corrupted copies
let seed = 99;
const rnd = () => ((seed = (seed * 1103515245 + 12345) % 2147483648) / 2147483648);
const pick = (a) => a[Math.floor(rnd() * a.length)];
const clone = (x) => JSON.parse(JSON.stringify(x));
const cases = [], checks = [];
for (let i = 0; i < 120; i++) {
	const base = clone(rnd() < .5 ? sets.demo : sets.seed); delete base.file;
	const k = Math.floor(rnd() * base.profiles.length);
	const p = base.profiles[k], r = base.rows.find((x) => x.employeeId === p.id);
	switch (Math.floor(rnd() * 14)) {
		case 0: if (r) r.ids = { tin: pick(['', '12345', '123-456-789-001', '999 888 777']) }; break;
		case 1: delete p.lastName; break;
		case 2: p.firstName = 'José "Pepe" & Co, O\'Neil|x'; break;
		case 3: p.region = 'BARMM'; delete p.regionNumber; break;
		case 4: p.region = 'Atlantis'; base.regionNumber = pick([undefined, 'NCR', '0001']); break;
		case 5: base.employer = { tin: pick(['900-000-000-123', '12', '900000000']), branch: pick([undefined, '12', '0007']) }; break;
		case 6: p.employmentStatus = pick(['X', 'CP', 'AL']); break;
		case 7: if (r) r.taxWithheld += pick([5, 0.5, 1000]); break;
		case 8: base.rows = []; break;
		case 9: p.hireDate = pick(['2026-06-15', '2010-01-01', undefined]); break;
		case 10: if (base.rows[1]) base.rows[1].ids = base.rows[0].ids; break;
		case 11: p.middleName = 'Ñañez Ä'; break;
		case 12: if (r) r.separation = { cause: pick(['retirement', 'redundancy']), date: '2026-09-30' }; break;
		case 13: p.nationality = 'x'.repeat(40); break;
	}
	let output; try { const b = buildAlphalist(base); output = { ...b, assumptions: undefined }; } catch (e) { output = { error: e.message }; }
	cases.push({ input: base, output });
	if (output.content) {
		let c = output.content;
		const lines = c.split('\r\n');
		const mut = Math.floor(rnd() * 12);
		if (mut === 0) c = c.replace(/\r\n/g, '\n');
		else if (mut === 1) c = c.replace('00000000000.00', '0000000000.001');
		else if (mut === 2) { const n = 1 + Math.floor(rnd() * (lines.length - 2)); lines[n] = lines[n].replace(/,000\d{3},/, ',000999,'); c = lines.join('\r\n'); }
		else if (mut === 3) c = c.replace(',"NCR",', ',"0001",');
		else if (mut === 4) { lines.splice(lines.length - 2, 1); c = lines.join('\r\n'); }
		else if (mut === 5) c = c.slice(0, -2);
		else if (mut === 6) c = c.replace('D1,"1604C","900000000"', 'D1,"1604C","900000001"');
		else if (mut === 7) c = c.replace(/(D1,[^\r]*?)"N"/, '$1"Q"');
		else if (mut === 8) c = 'H1604C,"999999999","0000",02/30/2026\r\n' + lines.slice(1).join('\r\n');
		else if (mut === 9) c = c.replace(/,"01\/01\/2026",/, ',"13/01/2026",');
		else if (mut === 10) { const n = 1 + Math.floor(rnd() * (lines.length - 3)); lines.splice(lines.length - 2, 0, lines[n]); c = lines.join('\r\n'); }
		else c = c + 'X9,"oops"\r\n';
		checks.push({ input: c, output: checkAlphalistDat(c) });
	}
}
checks.push({ input: '', output: checkAlphalistDat('') }, { input: 'é\r\n', output: checkAlphalistDat('é\r\n') });
writeFileSync(`${out}/alphalist.json`, JSON.stringify(cases));
writeFileSync(`${out}/alphalistCheck.json`, JSON.stringify(checks));
console.log('alphalist', cases.length, 'with errors', cases.filter((c) => c.output.errors?.length).length, '; check', checks.length, 'with errors', checks.filter((c) => c.output.errors.length).length);
