import { expect, test, type Page } from '@playwright/test'

async function login(page: Page, email: string) {
  await page.goto('/login')
  await page.getByLabel('Email').fill(email)
  await page.getByLabel('Password').fill('password')
  await page.getByRole('button', { name: 'Sign in' }).click()
  await expect(page).toHaveURL(/\/(dashboard|home|requests)$/)
}

test('admin creates, previews, reorders, and edits a dynamic request type', async ({
  page,
  browserName,
}) => {
  const errors: string[] = []
  page.on('console', (message) => {
    const expectedGuestCheck =
      message.location().url.includes('/api/v1/me') && message.text().includes('401')
    if (message.type() === 'error' && !expectedGuestCheck) errors.push(message.text())
  })
  page.on('pageerror', (error) => errors.push(error.message))
  await login(page, 'admin@opspilot.test')
  await page.goto('/request-types')
  await expect(page.getByRole('heading', { name: 'Request types' })).toBeVisible()
  await page.getByRole('link', { name: 'Create request type' }).click()
  await page.getByRole('button', { name: 'Save request type' }).click()
  await expect(page.getByText('Request type name is required.')).toBeVisible()

  const uniqueName = `E2E ${browserName} ${Date.now()}`
  await page.getByLabel('Name').fill(uniqueName)
  await page.getByRole('button', { name: 'Add field' }).click()
  await page.getByRole('button', { name: 'Add field' }).click()
  const fields = page.locator('article')
  const first = fields.nth(0)
  const second = fields.nth(1)
  await first.getByLabel('Label').fill('Summary')
  await first.getByLabel('Key').fill('summary')
  await second.getByLabel('Label').fill('Priority')
  await second.getByLabel('Key').fill('priority')
  await second.getByLabel('Field type').click()
  await page.getByRole('option', { name: 'Select', exact: true }).click()
  await second.getByLabel('Option 1 value').fill('high')
  await second.getByLabel('Option 1 label').fill('High')
  await second.getByRole('button', { name: 'Add option' }).click()
  await second.getByLabel('Option 2 value').fill('normal')
  await second.getByLabel('Option 2 label').fill('Normal')
  await second.getByRole('button', { name: /Move Priority up/ }).click()
  await page.getByRole('button', { name: 'Save request type' }).click()

  await expect(page).toHaveURL(/\/request-types\/\d+$/)
  await expect(page.getByRole('heading', { name: uniqueName })).toBeVisible()
  const schemaCards = page
    .locator('section')
    .filter({ hasText: 'Configured schema' })
    .locator('.bg-card')
  await expect(schemaCards.nth(0)).toContainText('1. Priority')
  await expect(schemaCards.nth(1)).toContainText('2. Summary')
  await expect(page.getByText('High (high)')).toBeVisible()

  await page.getByRole('link', { name: 'Edit request type' }).click()
  const priority = page.locator('article').nth(0)
  await priority.getByLabel('Label *required', { exact: true }).fill('Business priority')
  await priority.getByLabel('Option 2 label').fill('Standard')
  await page.setViewportSize({ width: 390, height: 844 })
  await expect(page.getByRole('button', { name: 'Add field' })).toBeVisible()
  await expect(priority.getByRole('button', { name: /Move Business priority down/ })).toBeVisible()
  await page.getByRole('button', { name: 'Save request type' }).click()
  await expect(page.getByText('1. Business priority')).toBeVisible()
  await expect(page.getByText('Standard (normal)')).toBeVisible()
  expect(errors).toEqual([])
})

test('requester follows backend request-type permission boundaries', async ({ page }) => {
  await login(page, 'requester@opspilot.test')
  await expect(page.getByRole('link', { name: 'Request Types' })).toHaveCount(0)
  await page.goto('/request-types')
  await expect(page).toHaveURL(/\/403$/)
  await expect(page.getByRole('heading', { name: 'Access denied' })).toBeVisible()
  await page.goto('/request-types/create')
  await expect(page).toHaveURL(/\/403$/)
})
