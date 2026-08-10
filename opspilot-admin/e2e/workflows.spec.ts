import { expect, test, type Page } from '@playwright/test'

async function login(page: Page, email: string) {
  await page.goto('/login')
  await page.getByLabel('Email').fill(email)
  await page.getByLabel('Password').fill('password')
  await page.getByRole('button', { name: 'Sign in' }).click()
  await expect(page).toHaveURL(/\/(dashboard|home|requests)$/)
}

async function createConditionalRequestType(page: Page, name: string) {
  await page.goto('/request-types/create')
  await page.getByLabel('Name').fill(name)
  await page.getByRole('button', { name: 'Add field' }).click()
  await page.getByRole('button', { name: 'Add field' }).click()
  await page.getByRole('button', { name: 'Add field' }).click()
  const field = page.locator('article').first()
  await field.getByLabel('Label').fill('Priority')
  await field.getByLabel('Key').fill('priority')
  await field.getByLabel('Field type').click()
  await page.getByRole('option', { name: 'Select', exact: true }).click()
  await field.getByLabel('Option 1 value').fill('high')
  await field.getByLabel('Option 1 label').fill('High')
  await field.getByRole('button', { name: 'Add option' }).click()
  await field.getByLabel('Option 2 value').fill('normal')
  await field.getByLabel('Option 2 label').fill('Normal')
  const amount = page.locator('article').nth(1)
  await amount.getByLabel('Label').fill('Amount')
  await amount.getByLabel('Key').fill('amount')
  await amount.getByLabel('Field type').click()
  await page.getByRole('option', { name: 'Number', exact: true }).click()
  const department = page.locator('article').nth(2)
  await department.getByLabel('Label').fill('Department')
  await department.getByLabel('Key').fill('department')
  await page.getByRole('button', { name: 'Save request type' }).click()
  await expect(page).toHaveURL(/\/request-types\/\d+$/)
}

test('admin builds, edits, publishes, and versions a conditional workflow', async ({
  page,
  browserName,
}) => {
  test.setTimeout(60_000)
  const errors: string[] = []
  page.on('console', (message) => {
    const expectedGuestCheck =
      message.location().url.includes('/api/v1/me') && message.text().includes('401')
    if (message.type() === 'error' && !expectedGuestCheck) errors.push(message.text())
  })
  page.on('pageerror', (error) => errors.push(error.message))
  await login(page, 'admin@opspilot.test')
  const requestTypeName = `E2E Workflow Type ${browserName} ${Date.now()}`
  await createConditionalRequestType(page, requestTypeName)

  await page.goto('/workflows')
  await expect(page.getByRole('heading', { name: 'Workflows' })).toBeVisible()
  await page.getByRole('link', { name: 'Create workflow' }).click()
  await page.getByRole('button', { name: 'Save workflow' }).click()
  await expect(page.getByText('Select a request type.')).toBeVisible()
  await page.getByLabel('Request type').selectOption({ label: requestTypeName })
  const workflowName = `E2E Approval ${browserName} ${Date.now()}`
  await page.getByLabel('Name').fill(workflowName)
  await page.getByRole('button', { name: 'Add step' }).click()
  await page.getByRole('button', { name: 'Add step' }).click()
  await page.getByLabel('Step name').nth(0).fill('Manager review')
  await page.getByLabel('Step name').nth(1).fill('Finance review')
  await page.getByLabel('Approver role').nth(0).selectOption('admin')
  await page.getByLabel('Approver role').nth(1).selectOption('approver')
  await page.getByRole('button', { name: 'Add condition' }).nth(1).click()
  await page.getByLabel('Condition 1 value').selectOption('high')
  await page.getByRole('button', { name: 'Add condition' }).nth(1).click()
  await page.getByLabel('Field').nth(1).selectOption({ label: 'Amount' })
  await page.getByLabel('Operator').nth(1).selectOption('greater_than')
  await page.getByLabel('Condition 2 value').fill('1250.5')
  await page.getByRole('button', { name: 'Add condition' }).nth(1).click()
  await page.getByLabel('Field').nth(2).selectOption({ label: 'Department' })
  await page.getByLabel('Operator').nth(2).selectOption('in')
  await page.getByRole('button', { name: 'Add value' }).click()
  await page.getByRole('button', { name: 'Add value' }).click()
  await page.getByLabel('Condition 3 value 1', { exact: true }).fill('finance')
  await page.getByLabel('Condition 3 value 2', { exact: true }).fill('operations')
  await page.getByRole('button', { name: 'Move step 2 up' }).click()
  await page.getByRole('button', { name: 'Save workflow' }).click()

  await expect(page).toHaveURL(/\/workflows\/\d+$/, { timeout: 15_000 })
  await expect(page.getByRole('heading', { name: workflowName })).toBeVisible()
  const flow = page.getByRole('heading', { name: 'Approval flow' }).locator('..')
  await expect(flow).toContainText('Step 1 · Finance review')
  await expect(flow).toContainText('Priority is equal to High')
  await expect(flow).toContainText('Amount is greater than 1,250.5')
  await expect(flow).toContainText('Department is one of finance, operations')
  await page.getByRole('link', { name: 'Edit draft' }).click()
  await page.getByLabel('Step name').first().fill('Finance approval')
  await page.setViewportSize({ width: 390, height: 844 })
  await expect(page.getByRole('button', { name: 'Add condition' }).first()).toBeVisible()
  await expect(page.getByRole('button', { name: 'Move step 1 down' })).toBeVisible()
  await page.getByRole('button', { name: 'Save workflow' }).click()
  await expect(page.getByText('Step 1 · Finance approval')).toBeVisible({ timeout: 15_000 })
  await page.getByRole('button', { name: 'Publish version' }).click()
  await expect(page.getByRole('alertdialog')).toContainText('becomes immutable')
  await page.getByRole('button', { name: 'Publish', exact: true }).click()
  await expect(page.getByText('active', { exact: true }).first()).toBeVisible()
  await expect(page.getByRole('link', { name: 'Edit draft' })).toHaveCount(0)
  await expect(page.getByRole('heading', { name: 'Version history' }).locator('..')).toContainText(
    'Version 1',
  )
  await page.getByRole('button', { name: 'Create new draft' }).click()
  await expect(page).toHaveURL(/\/workflows\/\d+\/edit$/, { timeout: 15_000 })
  expect(errors).toEqual([])
})

test('requester cannot access workflow administration', async ({ page }) => {
  await login(page, 'requester@opspilot.test')
  await expect(page.getByRole('link', { name: 'Workflows' })).toHaveCount(0)
  await page.goto('/workflows')
  await expect(page).toHaveURL(/\/403$/)
  await page.goto('/workflows/create')
  await expect(page).toHaveURL(/\/403$/)
})
