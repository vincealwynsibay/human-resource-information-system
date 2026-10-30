import { writeFileSync, rmSync } from 'node:fs';
const [src, out, tmp] = process.argv.slice(2);
const { LoanLedger } = await import('file:///' + src + '/src/loans.js');
let seed = 77;
const rnd = () => ((seed = (seed * 1103515245 + 12345) % 2147483648) / 2147483648);
const pick = (a) => a[Math.floor(rnd() * a.length)];
const scenarios = [];
for (let s = 0; s < 60; s++) {
	const file = `${tmp}/loans-${s}.json`; rmSync(file, { force: true });
	const ledger = new LoanLedger(file);
	const ids = []; const norm = (v) => JSON.parse(JSON.stringify(v ?? null, (k, x) => (typeof x === 'string' && ids.includes(x) ? 'L' + (ids.indexOf(x) + 1) : x)));
	const ops = [];
	const periods = [{ start: '2026-01-01', end: '2026-01-15' }, { start: '2026-01-16', end: '2026-01-31' }, { start: '2026-02-01', end: '2026-02-15' }];
	for (let i = 0; i < 14; i++) {
		const kind = i < 3 ? 'add' : pick(['add', 'inputsFor', 'inputsFor', 'post', 'post', 'remove']);
		let op, result;
		try {
			if (kind === 'add') {
				op = { kind, fields: { employeeId: pick(['A', 'B', '', 'A']), name: pick(['SSS salary loan', '  Pag-IBIG MPL ', '']), principal: pick([10000, '5000', 0, 2500.555, -5]), amortization: pick([1000, 2500.505, 0, 20000, '750']) } };
				const l = ledger.add(op.fields); ids.push(l.id); result = l;
			} else if (kind === 'inputsFor') {
				op = { kind, employeeId: pick(['A', 'B']), period: rnd() < .8 ? pick(periods) : null };
				result = ledger.inputsFor(op.employeeId, op.period ?? undefined);
			} else if (kind === 'post') {
				const per = pick(periods);
				const slips = ['A', 'B'].map((e) => ({ loans: ledger.inputsFor(e, per).map((x) => ({ loanId: x.loanId, deducted: Math.round(Math.min(x.amount, x.balance) * 100) / 100 })).concat(rnd() < .2 ? [{ loanId: 'ghost', deducted: 5 }, { loanId: null, deducted: 3 }, { loanId: ids[0], deducted: 0 }] : []) }));
				op = { kind, result: norm({ period: per, slips }), runId: 'run-' + i };
				result = ledger.post({ period: per, slips }, op.runId);
			} else {
				op = { kind, id: rnd() < .8 && ids.length ? pick(ids) : 'nope' };
				ledger.remove(op.id); result = null;
			}
		} catch (e) { result = { error: e.message, status: e.status }; }
		op = norm(op);
		ops.push({ op, result: norm(result) });
	}
	scenarios.push({ input: { ops: ops.map((o) => o.op) }, output: { results: ops.map((o) => o.result), final: norm(ledger.list()) } });
	rmSync(file, { force: true });
}
writeFileSync(`${out}/loans.json`, JSON.stringify(scenarios));
console.log(scenarios.length, 'scenarios', scenarios.flatMap((s) => s.output.results).filter((r) => r?.error).length, 'errors');
