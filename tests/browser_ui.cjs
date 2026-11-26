'use strict';

const { chromium } = require('playwright');
const assert = require('node:assert/strict');

const base = process.env.OPD_TEST_BASE_URL || 'http://127.0.0.1:8080';

async function sessionContext(browser, role, userId, viewport) {
  const context = await browser.newContext({ viewport });
  const sid = require('node:crypto').randomBytes(16).toString('hex');
  const response = await context.request.post(base + '/__test/session', {
    data: { sid, role, user_id: userId }
  });
  assert.equal(response.status(), 200, role + ' test session should initialise');
  await context.addCookies([{
    name: 'PHPSESSID', value: sid, domain: '127.0.0.1', path: '/',
    httpOnly: true, sameSite: 'Lax'
  }]);
  return context;
}

async function expectPage(context, path, heading) {
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  const response = await page.goto(base + path, { waitUntil: 'domcontentloaded' });
  assert.equal(response.status(), 200, path + ' should return HTTP 200');
  await page.getByRole('heading', { name: heading, exact: false }).first().waitFor();
  assert.deepEqual(errors, [], path + ' should not throw browser JavaScript errors');
  return page;
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    // Desktop role dashboards and animated-counter hooks.
    const adminCtx = await sessionContext(browser, 'Admin', 1, { width: 1365, height: 900 });
    const admin = await expectPage(adminCtx, '/admin/dashboard.php', 'Administrative Control Center');
    assert.equal(await admin.locator('[data-counter-key]').count(), 4, 'Admin dashboard renders four statistic counters');
    await expectPage(adminCtx, '/admin/queue.php', 'OPD Queue Control Centre');
    await adminCtx.close();

    const doctorCtx = await sessionContext(browser, 'Doctor', 2, { width: 1365, height: 900 });
    const doctor = await expectPage(doctorCtx, '/doctor/dashboard.php', 'Welcome, Dr Test Clinician');
    assert.equal(await doctor.locator('[data-counter-key]').count(), 3, 'Doctor dashboard renders three statistic counters');
    await expectPage(doctorCtx, '/doctor/queue.php', "Today's Patient Queue");
    await doctorCtx.close();

    // Mobile patient dashboard, drawer navigation, queue check-in and polling.
    const patientCtx = await sessionContext(browser, 'Patient', 3, { width: 390, height: 844 });
    const patient = await expectPage(patientCtx, '/patient/dashboard.php', 'Dashboard');
    assert.equal(await patient.locator('[data-counter-key]').count(), 4, 'Patient dashboard renders four statistic counters');
    const toggle = patient.locator('#sidebarToggleBtn');
    await toggle.waitFor({ state: 'visible' });
    await toggle.click();
    assert.equal(await patient.locator('#patientSidebar').evaluate(el => el.classList.contains('show')), true, 'Mobile sidebar opens');
    await patient.locator('#sidebarBackdrop').click({ position: { x: 350, y: 400 } });
    assert.equal(await patient.locator('#patientSidebar').evaluate(el => el.classList.contains('show')), false, 'Mobile sidebar closes using backdrop');

    const queue = await expectPage(patientCtx, '/patient/queue.php', 'My Patient Queue');
    const checkIn = queue.getByRole('button', { name: 'Check in now' }).first();
    await checkIn.waitFor({ state: 'visible' });
    await checkIn.click();
    await queue.getByText('Check-in successful.').waitFor();
    await queue.getByText('Checked in').waitFor();

    // Verify the patient queue page continues its scheduled refresh cycle.
    const poll = queue.waitForResponse(r => r.url().includes('/api/patient_queue.php') && r.request().method() === 'GET', { timeout: 18000 });
    await poll;
    await patientCtx.close();

    // Role-based page denial is rendered as HTTP 403, not an accidental dashboard.
    const deniedCtx = await sessionContext(browser, 'Patient', 3, { width: 390, height: 844 });
    const denied = await deniedCtx.newPage();
    const deniedResponse = await denied.goto(base + '/admin/queue.php');
    assert.equal(deniedResponse.status(), 403, 'Patient is denied the admin queue page');
    await deniedCtx.close();

    console.log('All Playwright desktop/mobile dashboard and queue checks passed.');
  } finally {
    await browser.close();
  }
})().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
