import { expect, test, type Page } from '@playwright/test'

async function login(page: Page, email: string) {
  await page.goto('/login')
  await page.getByLabel('Email').fill(email)
  await page.getByLabel('Password').fill('password')
  await page.getByRole('button', { name: 'Sign in' }).click()
  await expect(page).toHaveURL(/\/(dashboard|home|requests|approvals)$/)
}

test('requester saves, reopens, and submits a typed request draft', async ({
  page,
  browserName,
}) => {
  const errors: string[] = []
  page.on('pageerror', (error) => errors.push(error.message))
  await login(page, 'requester@opspilot.test')
  await page.getByRole('link', { name: 'Requests' }).click()
  await expect(page.getByRole('heading', { name: 'Requests' })).toBeVisible()
  await page.getByRole('link', { name: 'Create request' }).click()
  await page.getByLabel('Request type').selectOption({ label: 'Purchase Request' })
  await page.getByRole('button', { name: 'Submit request' }).click()
  await expect(page.getByText('This field is required.').first()).toBeVisible()
  const item = `E2E ${browserName} ${Date.now()}`
  await page.getByLabel('Item name').fill(item)
  await page.getByLabel('Estimated cost').fill('125.5')
  await page.getByLabel('Category').selectOption('hardware')
  await page.getByLabel('Justification').fill('Portable browser verification')
  await page.getByLabel('Urgent').check()
  await page.getByRole('button', { name: 'Save draft' }).click()
  await expect(page).toHaveURL(/\/requests\/\d+\/edit$/)
  await page.reload()
  await expect(page.getByLabel('Item name')).toHaveValue(item)
  await expect(page.getByLabel('Estimated cost')).toHaveValue('125.5')
  await page.setViewportSize({ width: 390, height: 844 })
  await expect(page.getByRole('button', { name: 'Submit request' })).toBeVisible()
  page.once('dialog', (dialog) => dialog.accept())
  await page.getByRole('button', { name: 'Submit request' }).click()
  await expect(page).toHaveURL(/\/requests\/\d+$/)
  await expect(page.getByText('Submitted', { exact: true })).toBeVisible()
  await expect(page.getByText(item)).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Approval plan' }).locator('..')).toContainText(
    'Manager Approval',
  )
  await expect(page.getByRole('link', { name: 'Edit draft' })).toHaveCount(0)
  await page.setViewportSize({ width: 1280, height: 720 })
  await page.getByLabel('Add comment').fill(`Collaboration note ${item}`)
  await page.getByRole('button', { name: 'Add comment' }).click()
  await expect(page.getByText(`Collaboration note ${item}`)).toBeVisible()
  const attachmentName = `e2e-${browserName}-${Date.now()}.txt`
  await page.getByLabel('Upload attachment').setInputFiles({
    name: attachmentName,
    mimeType: 'text/plain',
    buffer: Buffer.from('Disposable OpsPilot attachment'),
  })
  await page.locator('button').filter({ hasText: 'Upload attachment' }).click()
  await expect(page.getByText(attachmentName, { exact: true })).toBeVisible()
  const download = page.waitForEvent('download')
  await page.getByRole('button', { name: `Download ${attachmentName}` }).click()
  expect((await download).suggestedFilename()).toBe(attachmentName)
  await expect(page.getByRole('heading', { name: 'Activity' }).locator('..')).toContainText(
    'added a comment',
  )
  await expect(page.getByRole('heading', { name: 'Activity' }).locator('..')).toContainText(
    `uploaded ${attachmentName}`,
  )
  await page.getByRole('button', { name: 'Cancel request' }).click()
  const dialog = page.getByRole('alertdialog')
  await expect(dialog).toContainText('cancels the request and any active approval work')
  await dialog.getByRole('button', { name: 'Cancel request' }).click()
  await expect(page.getByText('Cancelled', { exact: true })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Approval plan' }).locator('..')).toContainText(
    'Cancelled',
  )
  await expect(page.getByRole('button', { name: 'Cancel request' })).toHaveCount(0)
  expect(errors).toEqual([])
})

test('approver cannot create requests', async ({ page }) => {
  await login(page, 'approver@opspilot.test')
  await expect(page.getByRole('link', { name: 'Create request' })).toHaveCount(0)
  await page.goto('/requests/create')
  await expect(page).toHaveURL(/\/403$/)
})
