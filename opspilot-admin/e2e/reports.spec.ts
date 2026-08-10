import { expect, test, type Page } from '@playwright/test'

async function login(page: Page, email: string) {
  await page.goto('/login')
  await page.getByLabel('Email').fill(email)
  await page.getByLabel('Password').fill('password')
  await page.getByRole('button', { name: 'Sign in' }).click()
  await expect(page).toHaveURL(/\/(dashboard|home|requests|approvals)$/)
}

test('reports-capable user explores dashboard and UTC reports', async ({ page }) => {
  await login(page, 'auditor@opspilot.test')
  await expect(page).toHaveURL(/\/dashboard$/)
  await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible()
  await expect(page.getByText('Total requests')).toBeVisible()
  await page.getByRole('link', { name: 'Request report' }).click()
  await expect(page.getByRole('heading', { name: 'Request report' })).toBeVisible()
  await page.getByLabel('From (UTC)').fill('2026-08-01')
  await page.getByLabel('To (UTC)').fill('2026-08-10')
  await page.getByRole('button', { name: 'Apply' }).click()
  await expect(page).toHaveURL(/from=2026-08-01.*to=2026-08-10/)
  await page.reload()
  await expect(page.getByLabel('From (UTC)')).toHaveValue('2026-08-01')
  await page.getByRole('button', { name: 'Reset' }).click()
  await expect(page).not.toHaveURL(/from=/)
  await page.getByRole('link', { name: 'Approval report' }).click()
  await expect(page.getByRole('heading', { name: 'Approval report' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Current pending workload' })).toBeVisible()
})

test('requester cannot access reports', async ({ page }) => {
  await login(page, 'requester@opspilot.test')
  await expect(page.getByRole('link', { name: 'Dashboard' })).toHaveCount(0)
  for (const path of ['/dashboard', '/reports/requests', '/reports/approvals']) {
    await page.goto(path)
    await expect(page).toHaveURL(/\/403$/)
  }
})
