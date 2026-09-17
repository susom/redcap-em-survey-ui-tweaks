/**
 * E2E regression probe: "Enhanced Drag and Drop Matrix Ranking" must not swallow clicks
 * meant for the fields rendered below the matrix.
 *
 * Background: hideDefault() parks REDCap's real matrix rows off-screen with
 * opacity:0 + position:absolute. opacity:0 still hit-tests, and an absolutely
 * positioned <tr> lets its inner table.headermatrix balloon to content width, so its
 * (invisible) box can land on top of the next field. pointer-events:none is what makes
 * clicks fall through. See ../matrix-ranking-branching-fix.md (root cause #3).
 *
 * Usage:
 *   npx playwright install chromium          # once, if needed
 *   SURVEY_URL='http://redcap.local/surveys/?s=XXXXXXXXXXXXXXXX' \
 *   MATRIX=testmatrix FIELD=text \
 *   node docs/e2e/matrix-dnd-clickthrough.mjs
 *
 * Env:
 *   SURVEY_URL       (required) public survey link of a survey whose first field group
 *                    is the drag/drop ranking matrix, with a text field right below it
 *   MATRIX           matrix_group_name configured for drag/drop (default: testmatrix)
 *   FIELD            field name of the text box below the matrix (default: text)
 *   PLAYWRIGHT_PATH  path to a playwright entry point, if not resolvable as 'playwright'
 *   SHOTDIR          where to drop screenshots (default: no screenshots)
 *
 * Exits non-zero if any check fails.
 */

const SURVEY_URL = process.env.SURVEY_URL;
const MATRIX     = process.env.MATRIX || 'testmatrix';
const FIELD      = process.env.FIELD  || 'text';
const SHOTDIR    = process.env.SHOTDIR || '';

if (!SURVEY_URL) {
    console.error('SURVEY_URL is required');
    process.exit(2);
}

const mod = await import(process.env.PLAYWRIGHT_PATH || 'playwright');
const { chromium, devices } = mod.default ?? mod;

const INPUT = `input[name="${FIELD}"]`;
const TYPED = 'clickthrough probe';
const failures = [];
const check = (ok, msg) => { console.log(`  ${ok ? 'PASS' : 'FAIL'}  ${msg}`); if (!ok) failures.push(msg); };

/** Is the text box the top-most element at its own centre point? */
const hitTest = page => page.evaluate(sel => {
    const i = document.querySelector(sel);
    if (!i) return { found: false };
    const r = i.getBoundingClientRect();
    const top = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2);
    return {
        found: true,
        isInput: top === i,
        topEl: top ? top.tagName.toLowerCase() + (top.id ? '#' + top.id : '') : null,
        rect: { y: Math.round(r.y), h: Math.round(r.height) }
    };
}, INPUT);

/** Computed pointer-events on the parked matrix rows (must be "none"). */
const pointerEvents = (page, matrix) => page.evaluate(
    m => [...document.querySelectorAll(`tr[mtxgrp='${m}']`)]
        .map(tr => ({ id: tr.id, pe: getComputedStyle(tr).pointerEvents })),
    matrix
);

/** Real mouse drag of one option into the ranking (target) list. */
async function dragIntoRanking(page, label) {
    const src = page.locator(`#sort_rank_${MATRIX} li`, { hasText: label });
    const dst = page.locator(`#sort_rank_target_${MATRIX}`);
    const sb = await src.boundingBox(), db = await dst.boundingBox();
    const tx = db.x + db.width / 2, ty = db.y + db.height - 25;
    const sx = sb.x + sb.width / 2, sy = sb.y + sb.height / 2;
    await page.mouse.move(sx, sy);
    await page.mouse.down();
    for (let i = 1; i <= 12; i++) {
        await page.mouse.move(sx + (tx - sx) * i / 12, sy + (ty - sy) * i / 12, { steps: 2 });
        await page.waitForTimeout(25);
    }
    await page.mouse.up();
    await page.waitForTimeout(900);
}

async function clickAndType(page, tag) {
    let clicked = true;
    try {
        await page.click(INPUT, { timeout: 6000 });
    } catch (e) {
        clicked = false;
        const why = String(e.message).split('\n').find(l => /intercepts pointer events/.test(l));
        console.log(`        ${(why || e.message.split('\n')[0]).trim()}`);
    }
    await page.keyboard.type(TYPED);
    const value = await page.inputValue(INPUT);
    check(clicked, `${tag}: real click reaches the text box`);
    check(value === TYPED, `${tag}: real keystrokes land in the text box (got "${value}")`);
    if (clicked) await page.fill(INPUT, '');
}

const browser = await chromium.launch({ headless: process.env.HEADED !== '1' });

for (const profile of [
    { name: 'desktop-1280x900', ctx: { viewport: { width: 1280, height: 900 } } },
    { name: 'mobile-iPhone13',  ctx: { ...devices['iPhone 13'] } }
]) {
    console.log(`\n=== ${profile.name} ===`);
    const ctx = await browser.newContext(profile.ctx);
    const page = await ctx.newPage();
    const jsErrors = [];
    // The LOCALHOST dev banner injected by redcap-docker-compose throws on survey pages; ignore it.
    page.on('pageerror', e => { if (!/offsetHeight/.test(e.message)) jsErrors.push(e.message); });
    page.on('console', m => { if (m.type() === 'error') jsErrors.push('console: ' + m.text()); });

    await page.goto(SURVEY_URL, { waitUntil: 'domcontentloaded', timeout: 45000 });
    await page.waitForTimeout(2500);

    const dnd = await page.evaluate(m => !!document.querySelector(`#sort_rank_${m}`), MATRIX);
    check(dnd, 'drag/drop UI rendered (module tweak is active)');
    if (!dnd) { await ctx.close(); continue; }

    const pe = await pointerEvents(page, MATRIX);
    check(pe.every(r => r.pe === 'none'),
        `parked matrix rows have pointer-events:none (${pe.map(r => r.id + '=' + r.pe).join(', ')})`);

    const before = await hitTest(page);
    check(before.found, `${FIELD} input exists`);
    check(before.isInput, `on load, text box is top-most at its centre (got ${before.topEl})`);

    await clickAndType(page, 'on load');

    // dragging must not undo the fix, and the field must still be usable afterwards
    const firstLabel = await page.locator(`#sort_rank_${MATRIX} li`).first().innerText();
    await dragIntoRanking(page, firstLabel.trim());
    const ranked = await page.evaluate(m =>
        [...document.querySelectorAll(`#sort_rank_target_${m} li`)].length, MATRIX);
    check(ranked === 1, `real mouse drag moved "${firstLabel.trim()}" into the ranking list`);

    const peAfter = await pointerEvents(page, MATRIX);
    check(peAfter.every(r => r.pe === 'none'), 'pointer-events:none survives a drag');

    const after = await hitTest(page);
    check(after.isInput, `after a drag, text box is still top-most (got ${after.topEl})`);
    await clickAndType(page, 'after drag');

    check(jsErrors.length === 0, `no JS errors (${JSON.stringify(jsErrors)})`);
    if (SHOTDIR) await page.screenshot({ path: `${SHOTDIR}/${profile.name}.png`, fullPage: true });
    await ctx.close();
}

await browser.close();

console.log(`\n${failures.length ? 'FAILED: ' + failures.length + ' check(s)' : 'ALL CHECKS PASSED'}`);
process.exit(failures.length ? 1 : 0);
