import { expect, test, type Page } from '@playwright/test'

async function login(page: Page, email: string) {
  await page.goto('/login')
  await page.getByLabel('Email').fill(email)
  await page.getByLabel('Password').fill('password')
  await page.getByRole('button', { name: 'Sign in' }).click()
  await expect(page).toHaveURL(/\/(dashboard|home|requests|approvals)$/)
}

async function logout(page: Page) {
  await page.getByRole('button', { name: 'Open account menu' }).click()
  await page.getByRole('menuitem', { name: 'Log out' }).click()
  await expect(page).toHaveURL(/\/login(?:\?|$)/)
}

async function submitPurchase(page: Page, label: string) {
  await page.goto('/requests/create')
  await page.getByLabel('Request type').selectOption({ label: 'Purchase Request' })
  await page.getByLabel('Item name').fill(label)
  await page.getByLabel('Estimated cost').fill('25')
  await page.getByLabel('Category').selectOption('office')
  await page.getByLabel('Justification').fill('Disposable approval inbox verification')
  page.once('dialog', (dialog) => dialog.accept())
  await page.getByRole('button', { name: 'Submit request' }).click()
  await expect(page).toHaveURL(/\/requests\/\d+$/)
  return Number(page.url().match(/\/requests\/(\d+)$/)?.[1])
}

async function decide(page: Page, requestId: number, decision: 'Approve' | 'Reject') {
  await page.goto('/approvals')
  const row = page.getByRole('row').filter({ hasText: `#${requestId}` })
  await expect(row).toBeVisible()
  await row.getByRole('button', { name: 'Review' }).click()
  await expect(page.getByRole('heading', { name: /Approval for request/ })).toBeVisible()
  await page.setViewportSize({ width: 390, height: 844 })
  await page.getByRole('button', { name: decision, exact: true }).click()
  const dialog = page.getByRole('dialog')
  await expect(dialog).toContainText(`${decision} request`)
  await dialog.getByRole('button', { name: decision, exact: true }).click()
  await expect(
    page.getByText(decision === 'Approve' ? 'Approved' : 'Rejected', { exact: true }).first(),
  ).toBeVisible()
  await expect(page.getByRole('button', { name: decision, exact: true })).toHaveCount(0)
}

test('assigned approver approves and rejects disposable requests', async ({
  page,
  browserName,
}) => {
  const errors: string[] = []
  page.on('pageerror', (error) => errors.push(error.message))
  await login(page, 'requester@opspilot.test')
  await expect(page.getByRole('link', { name: 'Approvals' })).toHaveCount(0)
  await page.goto('/approvals')
  await expect(page).toHaveURL(/\/403$/)
  const prefix = `E2E approval ${browserName} ${Date.now()}`
  const approveLabel = `${prefix} approve`
  const rejectLabel = `${prefix} reject`
  const approveRequestId = await submitPurchase(page, approveLabel)
  const rejectRequestId = await submitPurchase(page, rejectLabel)
  await logout(page)
  await login(page, 'admin@opspilot.test')
  await decide(page, approveRequestId, 'Approve')
  await page.setViewportSize({ width: 1280, height: 720 })
  await decide(page, rejectRequestId, 'Reject')
  expect(errors).toEqual([])
})
