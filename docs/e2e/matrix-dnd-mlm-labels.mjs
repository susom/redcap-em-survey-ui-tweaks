/**
 * Regression probe: "Enhanced Drag and Drop Matrix Ranking" must show Multi-Language Management (MLM)
 * translations of the row labels, and follow the participant's language switches.
 *
 * Background: the drag/drop items are built from data dictionary labels (reference language), while MLM
 * translates REDCap's own matrix rows, which hideDefault() parks off screen. The module now copies each
 * row's translated label into its drag/drop item from REDCap.MultiLanguage.onLangChanged().
 * See ../matrix-ranking-mlm-labels-fix.md.
 *
 * This is a standalone harness (no REDCap instance needed): it renders REDCap-like ranked matrix markup,
 * the module's three JS files in their required order, and a stand-in for REDCap.MultiLanguage with the
 * same public surface the module uses (onLangChanged, isInitialized) and the same behavior (replace the
 * contents of [data-mlm-field][data-mlm-type="label"], then notify subscribers). Drags are real mouse drags.
 *
 * Usage:
 *   npm i playwright jquery@3 && npx playwright install chromium     # once, in any scratch folder
 *   JQUERY_PATH=/path/to/node_modules/jquery/dist/jquery.min.js \
 *   PLAYWRIGHT_PATH=/path/to/node_modules/playwright/index.mjs \
 *   node docs/e2e/matrix-dnd-mlm-labels.mjs
 *
 * Env:
 *   JQUERY_PATH      (required) path to a jQuery 3.x build
 *   PLAYWRIGHT_PATH  path to a playwright entry point, if not resolvable as 'playwright'
 *   BASELINE_JS      optional path to an older matrixranking.js; asserts the bug reproduces with it
 *
 * Exits non-zero if any check fails.
 */

import fs from 'fs';
import { fileURLToPath } from 'url';

if (!process.env.JQUERY_PATH) {
    console.error('JQUERY_PATH is required');
    process.exit(2);
}
const mod = await import(process.env.PLAYWRIGHT_PATH || 'playwright');
const { chromium } = mod.default ?? mod;
const MOD = fileURLToPath(new URL('../../js/', import.meta.url));
const JQ = fs.readFileSync(process.env.JQUERY_PATH,'utf8');
const SORT = fs.readFileSync(MOD+'sortable.min.js','utf8') + '\n' + fs.readFileSync(MOD+'jquery-sortable.js','utf8');
const EN = ['Advance notice that negative results may be provided','Contact information for support services','Having a support person present'];
const ES = ['Aviso previo de que se pueden proporcionar resultados negativos','Información de contacto para los servicios de apoyo','Tener una persona de apoyo presente'];
const NAMES = ['negative_steps1','negative_steps2','negative_steps3'];
const GRP='negative_steps';

function page({ rankJs, mlm, mlmTiming = 'after', saved = {} }) {
  const rows = NAMES.map((n,i)=>`
  <tr id="${n}-tr" sq_id="${n}" mtxgrp="${GRP}"><td class="labelmatrix"><div data-kind="field-label"><div data-mlm-field="${n}" data-mlm-type="label">${EN[i]}</div></div></td>
   <td class="data choicematrix">${[1,2,3].map(v=>`<input type="radio" id="mtxopt-${n}_${v}" name="${n}___radio" value="${v}" onclick="matrix_rank(${v},'${n}','${GRP}');document.forms['form'].${n}.value=this.value;doBranching('${n}');">`).join('')}
   <input type="hidden" name="${n}" value="${saved[n]||''}"></td></tr>`).join('');
  // Stand-in MLM: same public surface the module uses (onLangChanged / isInitialized), and like the real one it
  // translates by replacing the contents of [data-mlm-field][data-mlm-type=label] and then notifies subscribers.
  const mlmJs = !mlm ? '' : `
  window.REDCap = window.REDCap || {};
  (function(){
    var cbs=[], init=false, ES=${JSON.stringify(ES)}, EN=${JSON.stringify(EN)}, NAMES=${JSON.stringify(NAMES)};
    function apply(lang){ NAMES.forEach(function(n,i){ $('[data-mlm-field="'+n+'"][data-mlm-type="label"]').html(lang=='es'?ES[i]:EN[i]); });
      cbs.forEach(function(cb){ cb(lang,false); }); }
    REDCap.MultiLanguage = { onLangChanged:function(cb){ cbs.push(cb); return cbs.length; }, isInitialized:function(){ return init; },
      getCurrentLanguage:function(){ return window.__lang; }, __switch:function(l){ window.__lang=l; apply(l); } };
    function start(){ window.__lang='es'; apply('es'); init=true; }
    ${mlmTiming==='before' ? "$(start);" : "$(function(){ Promise.resolve().then(function(){ setTimeout(start,50); }); });"}
  })();`;
  return `<!doctype html><html><head><meta charset="utf-8"><script>${JQ}</script>
  <script>var log=[]; function matrix_rank(){} function doBranching(f){log.push('branch:'+f);} function radioResetVal(f){document.forms['form'][f].value='';}</script>
  <script>${mlmJs}</script></head><body><form name="form" id="form"><table>
  <tr id="${NAMES[0]}-sh-tr"><td>Header</td></tr>${rows}</table></form>
  <script>var MatrixRanking = MatrixRanking || {}; MatrixRanking.config = ${JSON.stringify({[GRP]:{choices:{1:'#1',2:'#2',3:'#3'},header:'Header',names:NAMES,labels:EN,options:{show_rank_label:true,matrix_instructions:'',randomize_options:false}}})};
  ${SORT}
  ${rankJs}</script></body></html>`;
}

const failures=[]; const check=(ok,msg)=>{console.log(`  ${ok?'PASS':'FAIL'}  ${msg}`); if(!ok) failures.push(msg);};
const b = await chromium.launch();
async function load(opts){ const p = await b.newPage({viewport:{width:1200,height:900}}); const errs=[]; p.on('pageerror',e=>errs.push(e.message));
  await p.setContent(page(opts)); await p.waitForTimeout(400); p.__errs=errs; return p; }
const texts = (p,id)=>p.$$eval(`#${id} li`, ls=>ls.map(l=>{const s=l.querySelector('.mtx_rank_text'); return (s?s.textContent:l.textContent).trim();}));
async function drag(p,label){ const src=p.locator(`#sort_rank_${GRP} li`,{hasText:label}); const dst=p.locator(`#sort_rank_target_${GRP}`);
  const sb=await src.boundingBox(), db=await dst.boundingBox(); const sx=sb.x+sb.width/2, sy=sb.y+sb.height/2, tx=db.x+db.width/2, ty=db.y+db.height-25;
  await p.mouse.move(sx,sy); await p.mouse.down(); for(let i=1;i<=12;i++){ await p.mouse.move(sx+(tx-sx)*i/12, sy+(ty-sy)*i/12,{steps:2}); await p.waitForTimeout(25);} await p.mouse.up(); await p.waitForTimeout(400); }
const vals = p=>p.evaluate(n=>n.map(x=>document.forms['form'][x].value), NAMES);

const FIXED = fs.readFileSync(MOD+'matrixranking.js','utf8');
let p;

if (process.env.BASELINE_JS) {
    console.log('Baseline (BASELINE_JS), MLM on, Spanish selected');
    p = await load({rankJs:fs.readFileSync(process.env.BASELINE_JS,'utf8'), mlm:true});
    check(JSON.stringify(await texts(p,`sort_rank_${GRP}`))===JSON.stringify(EN), 'bug reproduces: drag items stay in the reference language');
    await p.close();
}

console.log('A. No MLM on page (regression)');
p = await load({rankJs:FIXED, mlm:false});
check(JSON.stringify(await texts(p,`sort_rank_${GRP}`))===JSON.stringify(EN), 'items show data dictionary labels');
await drag(p, EN[1]);
check((await vals(p))[1]==='1', 'dragged item saves rank 1 to the hidden matrix');
check(await p.locator(`#sort_rank_target_${GRP} li .badge`).count()===1, 'rank badge shown');
check(p.__errs.length===0, 'no JS errors '+p.__errs.join('; ')); await p.close();

console.log('B. MLM initializes after the module (Spanish)');
p = await load({rankJs:FIXED, mlm:true, mlmTiming:'after'});
check(JSON.stringify(await texts(p,`sort_rank_${GRP}`))===JSON.stringify(ES), 'items show Spanish labels');
await drag(p, ES[2]); await drag(p, ES[0]);
const v=await vals(p); check(v[2]==='1' && v[0]==='2', 'ranks save in drag order ('+v.join(',')+')');
check(JSON.stringify(await texts(p,`sort_rank_target_${GRP}`))===JSON.stringify([ES[2],ES[0]]), 'ranked items keep Spanish text after drag');
console.log('C. Switch language to English, then back to Spanish');
await p.evaluate(()=>REDCap.MultiLanguage.__switch('en')); await p.waitForTimeout(100);
check(JSON.stringify(await texts(p,`sort_rank_target_${GRP}`))===JSON.stringify([EN[2],EN[0]]) && JSON.stringify(await texts(p,`sort_rank_${GRP}`))===JSON.stringify([EN[1]]), 'all items switch to English');
check(await p.locator(`#sort_rank_target_${GRP} li .badge`).count()===2, 'rank badges survive the switch');
check(JSON.stringify(await vals(p))===JSON.stringify(v), 'saved ranks unchanged by the switch');
await p.evaluate(()=>REDCap.MultiLanguage.__switch('es')); await p.waitForTimeout(100);
check(JSON.stringify(await texts(p,`sort_rank_target_${GRP}`))===JSON.stringify([ES[2],ES[0]]), 'items switch back to Spanish');
check(p.__errs.length===0, 'no JS errors '+p.__errs.join('; ')); await p.close();

console.log('D. MLM already initialized before the module subscribes');
p = await load({rankJs:FIXED, mlm:true, mlmTiming:'before'});
check(JSON.stringify(await texts(p,`sort_rank_${GRP}`))===JSON.stringify(ES), 'items show Spanish labels');
check(p.__errs.length===0, 'no JS errors'); await p.close();

console.log('E. Resumed survey with a saved rank (Spanish)');
p = await load({rankJs:FIXED, mlm:true, saved:{negative_steps2:'1'}});
check(JSON.stringify(await texts(p,`sort_rank_target_${GRP}`))===JSON.stringify([ES[1]]), 'previously ranked item shows Spanish in the ranking list');
check(JSON.stringify(await texts(p,`sort_rank_${GRP}`))===JSON.stringify([ES[0],ES[2]]), 'unranked items show Spanish');
check(p.__errs.length===0, 'no JS errors'); await p.close();

await b.close();
console.log(failures.length ? `\nFAILED: ${failures.length} check(s)` : '\nALL CHECKS PASSED'); process.exit(failures.length?1:0);
