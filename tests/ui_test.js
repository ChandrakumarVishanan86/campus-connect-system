/* Playwright UI test + screenshot generator for CampusConnect.
 *   npm i playwright && npx playwright install chromium
 *   BASE=http://localhost/campus_connect node tests/ui_test.js
 * Optional: CDN_ROOT=/path/with/node_modules (bootstrap, bootstrap-icons, jquery, flatpickr, chart.js) serves the CDN assets locally for offline runs.
 * Re-import sql/database.sql before every run (the test creates posts, events and RSVPs). */
const { chromium } = require('playwright'); const fs = require('fs'); const { execSync } = require('child_process'); const path = require('path');
const B = (process.env.BASE || 'http://localhost:8080/campus_connect').replace(/\/$/, ''), OUT = path.join(__dirname, '..', 'docs', 'screenshots') + '/';
const MYSQL = process.env.MYSQL || 'mysql -uroot', DB = process.env.DB_NAME || 'campus_connect', N = (process.env.CDN_ROOT || '') + '/node_modules/';
const sql = q => execSync(`${MYSQL} ${DB} -N -B -e "${q}"`).toString().trim();
const MAP = [[/bootstrap@5.3.3\/dist\/js\/bootstrap.bundle.min.js/, 'bootstrap/dist/js/bootstrap.bundle.min.js', 'application/javascript'], [/bootstrap@5.3.3\/dist\/css\/bootstrap.min.css/, 'bootstrap/dist/css/bootstrap.min.css', 'text/css'],
  [/bootstrap-icons@1.11.3\/font\/bootstrap-icons.min.css/, 'bootstrap-icons/font/bootstrap-icons.min.css', 'text/css'], [/bootstrap-icons.*\.woff2/, 'bootstrap-icons/font/fonts/bootstrap-icons.woff2', 'font/woff2'],
  [/jquery-3.7.1.min.js/, 'jquery/dist/jquery.min.js', 'application/javascript'], [/jquery.dataTables.min.js/, 'datatables.net/js/jquery.dataTables.min.js', 'application/javascript'], [/dataTables.bootstrap5.min.js/, 'datatables.net-bs5/js/dataTables.bootstrap5.min.js', 'application/javascript'], [/dataTables.bootstrap5.min.css/, 'datatables.net-bs5/css/dataTables.bootstrap5.min.css', 'text/css'], [/flatpickr.min.js/, 'flatpickr/dist/flatpickr.min.js', 'application/javascript'], [/flatpickr.min.css/, 'flatpickr/dist/flatpickr.min.css', 'text/css'], [/chart.umd.min.js/, 'chart.js/dist/chart.umd.js', 'application/javascript']];
async function cdn(ctx) { if (!process.env.CDN_ROOT) return; await ctx.route(/^https:\/\/(cdn|code|fonts)\./, async r => { const u = r.request().url(), m = MAP.find(x => x[0].test(u)); if (m) return r.fulfill({ body: fs.readFileSync(N + m[1]), contentType: m[2] }); r.abort(); }); }
(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const br = await chromium.launch(), errs = [], steps = []; const ok = (n, c) => steps.push((c ? 'PASS ' : 'FAIL ') + n);
  async function sess(u, p, vp = { width: 1440, height: 900 }) {
    const ctx = await br.newContext({ viewport: vp }); await cdn(ctx); const pg = await ctx.newPage();
    pg.on('pageerror', e => errs.push(u + ' ' + pg.url() + ' PAGEERR ' + e.message));
    pg.on('console', m => { if (m.type() === 'error' && !/net::ERR|Failed to load resource/.test(m.text())) errs.push(u + ' ' + pg.url() + ' CONSOLE ' + m.text()); });
    pg.on('response', r => { if (r.status() >= 500) errs.push('HTTP ' + r.status() + ' ' + r.url()); });
    if (u) { await pg.goto(B + '/login'); await pg.fill('[name=username]', u); await pg.fill('[name=password]', p); await Promise.all([pg.waitForNavigation(), pg.click('button[type=submit]')]); await pg.waitForLoadState('networkidle'); }
    return pg;
  }
  const snap = async (pg, p, name, wait = 1000, full = false) => { await pg.goto(B + '/' + p, { waitUntil: 'networkidle' }); await pg.waitForTimeout(wait); await pg.screenshot({ path: OUT + name + '.png', fullPage: full }); };

  // ---------- public
  const pub = await sess(null); await pub.goto(B + '/login', { waitUntil: 'networkidle' }); await pub.waitForTimeout(500); await pub.screenshot({ path: OUT + 'login.png' });
  ok('login page has demo chips', (await pub.$$('#demoBox [data-u]')).length >= 3);
  await pub.goto(B + '/register', { waitUntil: 'networkidle' }); await pub.screenshot({ path: OUT + 'register.png' });
  await pub.goto(B + '/feed'); ok('anonymous visitors are sent to login', /login/.test(pub.url()));

  // ---------- student
  const s = await sess('student2', 'student123'); await s.waitForTimeout(1200);
  ok('student lands on the feed', /\/feed$/.test(s.url()));
  ok('three-column layout (rail + feed + widgets)', !!(await s.$('.cc-side')) && !!(await s.$('.cc-rail')));
  await s.waitForTimeout(800); await s.screenshot({ path: OUT + 'feed.png' });
  ok('feed posts rendered', (await s.$$('#feed .post')).length >= 5);
  ok('right rail widgets loaded', (await s.$$('#railEvents *')).length > 0);
  await s.click('#composerCard'); await s.waitForSelector('#composeModal.show'); await s.waitForTimeout(400);
  await s.fill('#cpBody', 'Anyone up for a study group for the Database module this Friday? #HNDIT');
  ok('character counter updates', /\d+ \/ 2000/.test(await s.textContent('#cpCount')) && !/^0 /.test(await s.textContent('#cpCount')));
  await s.screenshot({ path: OUT + 'compose.png' });
  await s.selectOption('#cpType', 'Question'); await s.click('#cpGo'); await s.waitForTimeout(1200);
  ok('new post appears in the feed', /study group/.test(await s.textContent('#feed')));
  const first = s.locator('#feed .post', { hasText: 'study group' }).first(); await first.locator('[data-a=like]').click(); await s.waitForTimeout(500);
  ok('like toggles', (await first.locator('.like').getAttribute('class')).includes('on'));
  await first.locator('[data-a=comments]').click(); await s.waitForTimeout(500); await first.locator('.cform input').fill('Count me in!'); await first.locator('.cform button').click(); await s.waitForTimeout(900);
  ok('comment added', /Count me in/.test(await first.textContent()));
  await s.screenshot({ path: OUT + 'feed-comments.png' });
  await s.locator('#feed .post').nth(2).locator('.dropdown button.act').click().catch(() => {}); await s.waitForTimeout(300);
  await s.keyboard.press('Escape');
  await s.click('#typeChips [data-type=Announcement]'); await s.waitForTimeout(900); ok('announcement filter works', (await s.$$('#feed .post')).length >= 1);

  // events
  await snap(s, 'events', 'events-upcoming'); ok('events listed', (await s.$$('#evList .ev-card')).length >= 5);
  await s.click('#evTabs [data-scope=calendar]'); await s.waitForTimeout(900); await s.screenshot({ path: OUT + 'events-calendar.png' }); ok('calendar grid has cells', (await s.$$('#calGrid .cell')).length >= 28);
  const evId = sql(`SELECT e.id FROM events e WHERE e.status='Approved' AND e.starts_at > NOW() + INTERVAL 2 DAY AND (e.capacity IS NULL OR e.capacity > 30) AND NOT EXISTS (SELECT 1 FROM event_rsvps r WHERE r.event_id=e.id AND r.user_id=9) AND NOT EXISTS (SELECT 1 FROM event_rsvps r JOIN events x ON x.id=r.event_id WHERE r.user_id=9 AND r.status='Going' AND x.starts_at < e.ends_at AND x.ends_at > e.starts_at) ORDER BY e.starts_at LIMIT 1`);
  await s.goto(B + '/events/' + evId, { waitUntil: 'networkidle' }); await s.waitForTimeout(700); await s.screenshot({ path: OUT + 'event-detail.png', fullPage: true });
  await s.click('#evView [data-a=rsvp][data-s=Going]'); await s.waitForTimeout(1000);
  ok('RSVP going via UI', sql(`SELECT status FROM event_rsvps WHERE event_id=${evId} AND user_id=9`) === 'Going');
  await s.screenshot({ path: OUT + 'event-rsvped.png' });
  await s.click('#evView [data-a=cancel_rsvp]'); await s.waitForTimeout(900); ok('RSVP cancelled via UI', sql(`SELECT COUNT(*) FROM event_rsvps WHERE event_id=${evId} AND user_id=9`) === '0');
  // student proposes an event
  await s.goto(B + '/events', { waitUntil: 'networkidle' }); await s.click('#btnNew'); await s.waitForSelector('#evModal.show'); await s.waitForTimeout(500);
  const d = new Date(Date.now() + 100 * 864e5), ds = d.toISOString().slice(0, 10);
  await s.fill('#evTitle', 'HNDIT Board Game Night'); await s.selectOption('#evVenue', 'Library Hall'); await s.selectOption('#evCat', 'Social');
  await s.evaluate(v => { document.querySelector('#evStart')._flatpickr.setDate(v[0] + ' 16:00', true); document.querySelector('#evEnd')._flatpickr.setDate(v[0] + ' 18:00', true); }, [ds]);
  await s.fill('#evCap', '40'); await s.fill('#evDesc', 'Bring your favourite board games and snacks.'); await s.screenshot({ path: OUT + 'event-form.png' });
  await s.click('#evSave'); await s.waitForTimeout(1200); ok('student proposal saved as Pending', sql("SELECT status FROM events WHERE title='HNDIT Board Game Night'") === 'Pending');
  const pendId = sql("SELECT id FROM events WHERE title='HNDIT Board Game Night'");

  // clubs
  await snap(s, 'clubs', 'clubs'); ok('clubs grid', (await s.$$('#clubGrid .club-card')).length >= 10);
  const openClub = sql("SELECT c.id FROM clubs c WHERE c.join_policy='Open' AND c.is_active=1 AND NOT EXISTS (SELECT 1 FROM club_members m WHERE m.club_id=c.id AND m.user_id=9) ORDER BY c.id LIMIT 1");
  await s.goto(B + '/clubs/' + openClub, { waitUntil: 'networkidle' }); await s.waitForTimeout(800); await s.screenshot({ path: OUT + 'club-detail.png' });
  await s.click('#clubHead [data-a=join]'); await s.waitForTimeout(1000); ok('joined open club via UI', sql(`SELECT status FROM club_members WHERE club_id=${openClub} AND user_id=9`) === 'Active');
  await s.click('[data-bs-target="#tMembers"]'); await s.waitForTimeout(700); await s.screenshot({ path: OUT + 'club-members.png' });

  // lost & found
  await snap(s, 'lost-found', 'lost-found'); ok('lost & found cards', (await s.$$('#lfGrid .item-card')).length >= 3);
  await s.click('#btnNew'); await s.waitForSelector('#lfModal.show'); await s.waitForTimeout(400);
  await s.fill('#lfTitle', 'Black umbrella'); await s.fill('#lfLoc', 'Canteen entrance'); await s.fill('#lfDesc', 'Has a wooden handle'); await s.screenshot({ path: OUT + 'lost-form.png' });
  await s.click('#lfForm .btn-gradient'); await s.waitForTimeout(1100); ok('lost report created via UI', sql("SELECT COUNT(*) FROM lost_items WHERE title='Black umbrella'") === '1');

  // people, profile, notifications
  await snap(s, 'people', 'people'); ok('people directory', (await s.$$('#pGrid .card-panel')).length >= 20);
  await snap(s, 'people/8', 'profile-public', 1200, true);
  await snap(s, 'profile', 'profile-edit');
  await s.fill('#pfBio', 'Database nerd and chess player.'); await s.click('#pfForm button.btn-gradient'); await s.waitForTimeout(900); ok('profile saved via UI', sql("SELECT bio FROM users WHERE id=9") === 'Database nerd and chess player.');
  await snap(s, 'notifications', 'notifications'); ok('notifications listed', (await s.$$('#nList .nrow')).length >= 3);
  await s.click('#markAll'); await s.waitForTimeout(800); ok('mark all read', sql('SELECT COUNT(*) FROM notifications WHERE user_id=9 AND is_read=0') === '0');
  // dark mode
  await s.goto(B + '/feed', { waitUntil: 'networkidle' }); await s.click('#themeBtn'); await s.waitForTimeout(600); ok('dark theme applied', (await s.getAttribute('html', 'data-theme')) === 'dark'); await s.screenshot({ path: OUT + 'feed-dark.png' });
  await snap(s, 'events', 'events-dark');

  // ---------- lecturer
  const l = await sess('lecturer1', 'staff123');
  await l.click('#composerCard'); await l.waitForSelector('#composeModal.show'); await l.waitForTimeout(400); await l.selectOption('#cpType', 'Announcement'); await l.fill('#cpTitle', 'Semester 3 exam timetable released'); await l.fill('#cpBody', 'The timetable is now on the portal. Please check your clashes before Friday.');
  await l.selectOption('#cpProg', { index: 1 }); await l.screenshot({ path: OUT + 'announcement-form.png' }); await l.click('#cpGo'); await l.waitForTimeout(1200);
  ok('staff announcement published', sql("SELECT COUNT(*) FROM posts WHERE title='Semester 3 exam timetable released'") === '1');
  await l.goto(B + '/events/' + evId, { waitUntil: 'networkidle' }); await l.waitForTimeout(600);

  // ---------- admin
  const a = await sess('admin', 'admin123'); await a.waitForTimeout(800);
  await a.goto(B + '/events/' + pendId, { waitUntil: 'networkidle' }); await a.waitForTimeout(800); await a.screenshot({ path: OUT + 'event-approval.png' });
  await a.click('#evView [data-a=approve]'); await a.waitForTimeout(1000); ok('admin approved via UI', sql(`SELECT status FROM events WHERE id=${pendId}`) === 'Approved');
  const live = sql("SELECT id FROM events WHERE status='Approved' AND starts_at > NOW() LIMIT 1");
  sql(`UPDATE events SET starts_at = NOW() - INTERVAL 5 MINUTE, ends_at = NOW() + INTERVAL 100 MINUTE WHERE id=${live}`); sql(`INSERT IGNORE INTO event_rsvps (event_id,user_id,status) VALUES (${live},9,'Going'),(${live},10,'Going')`);
  await a.goto(B + '/events/' + live, { waitUntil: 'networkidle' }); await a.waitForTimeout(1000); await a.screenshot({ path: OUT + 'event-checkin-organiser.png', fullPage: true });
  await a.locator('#mgr [data-a=ci]').first().click(); await a.waitForTimeout(900); ok('organiser checks a guest in via UI', Number(sql(`SELECT COUNT(*) FROM event_rsvps WHERE event_id=${live} AND checked_in_at IS NOT NULL`)) >= 1);
  const code = sql(`SELECT checkin_code FROM events WHERE id=${live}`);
  const s2 = await sess('student3', 'student123'); await s2.goto(B + '/events/' + live, { waitUntil: 'networkidle' }); await s2.waitForTimeout(800);
  await s2.fill('#ciCode', '000000'); await s2.click('#ciForm button'); await s2.waitForTimeout(700); await s2.fill('#ciCode', code); await s2.click('#ciForm button'); await s2.waitForTimeout(1000);
  ok('student checks in with the code via UI', sql(`SELECT checked_in_at IS NOT NULL FROM event_rsvps WHERE event_id=${live} AND user_id=10`) === '1'); await s2.screenshot({ path: OUT + 'event-checkin-student.png' });
  // moderation
  const pid = sql("SELECT id FROM posts WHERE status='Published' AND user_id=12 ORDER BY id LIMIT 1");
  for (const u of [3, 4, 5]) sql(`INSERT IGNORE INTO reports (entity, entity_id, reporter_id, reason) VALUES ('post', ${pid}, ${u + 7}, 'Spam')`);
  await snap(a, 'admin/moderation', 'admin-moderation'); ok('moderation queue has items', (await a.$$('#modList .card-panel, #modList tr, #modList .post')).length >= 1 || /report/i.test(await a.textContent('body')));
  await snap(a, 'admin/insights', 'admin-insights', 2000, true); ok('insight charts drawn', (await a.$$('canvas')).length >= 3);
  await snap(a, 'admin/users', 'admin-users'); await snap(a, 'admin/clubs', 'admin-clubs'); await snap(a, 'admin/programmes', 'admin-programmes'); await snap(a, 'admin/audit', 'admin-audit');

  // ---------- mobile
  const m = await sess('student2', 'student123', { width: 390, height: 844 }); await m.waitForTimeout(1200); await m.screenshot({ path: OUT + 'feed-mobile.png' });
  ok('mobile bottom tab bar visible', await m.isVisible('.tabbar'));
  await snap(m, 'events', 'events-mobile'); await snap(m, 'events/' + evId, 'event-mobile', 900, true);
  ok('no horizontal overflow on mobile event page', await m.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1));
  await snap(m, 'clubs', 'clubs-mobile'); ok('no horizontal overflow on mobile clubs', await m.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1));
  await snap(m, 'events', 'events-mobile-cal'); await m.click('#evTabs [data-scope=calendar]'); await m.waitForTimeout(800); ok('no horizontal overflow on mobile calendar', await m.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1));

  console.log(steps.join('\n')); console.log('\nERRORS:', errs.length ? '\n' + [...new Set(errs)].join('\n') : 'none');
  await br.close(); process.exit(steps.some(s => s.startsWith('FAIL')) || errs.length ? 1 : 0);
})();
