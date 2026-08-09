import { expect, test, type Page } from '@playwright/test'

async function switchWorkspaceWhenAvailable(page: Page) {
  const switcher = page.locator('button[aria-label="Switch workspace"]')
  const original = await switcher.innerText()
  await switcher.click()
  const workspaceItems = page.getByRole('menuitem')
  if ((await workspaceItems.count()) <= 1) {
    await page.keyboard.press('Escape')
    return original.trim()
  }
  await workspaceItems.filter({ hasNotText: original.trim() }).first().click()
  await expect(switcher).not.toHaveText(original.trim())
  return (await switcher.innerText()).trim()
}

test('session auth, workspace context, errors, and logout work with Laravel', async ({ page }) => {
  const errors: string[] = []
  page.on('console', (message) => {
    const expectedGuestCheck =
      message.location().url.includes('/api/v1/me') && message.text().includes('401')
    if (message.type() === 'error' && !expectedGuestCheck) errors.push(message.text())
  })
  page.on('pageerror', (error) => errors.push(error.message))

  await page.goto('/home?from=e2e')
  await expect(page.getByRole('heading', { name: 'Sign in to OpsPilot' })).toBeVisible()
  expect(new URL(page.url()).searchParams.get('redirect')).toBe('/home?from=e2e')

  await page.getByLabel('Email').fill('owner@opspilot.test')
  await page.getByLabel('Password').fill('password')
  await page.getByRole('button', { name: 'Sign in' }).click()
  await expect(page).toHaveURL(/\/home\?from=e2e$/)
  await expect(page.getByRole('heading', { name: 'Welcome to OpsPilot' })).toBeVisible()
  await expect(page.getByRole('button', { name: 'Open account menu' })).toBeVisible()

  await page.reload()
  await expect(page).toHaveURL(/\/home\?from=e2e$/)
  await expect(page.getByText('owner@opspilot.test')).toBeVisible()

  const selectedWorkspace = await switchWorkspaceWhenAvailable(page)
  await page.reload()
  await expect(page.locator('button[aria-label="Switch workspace"]')).toContainText(
    selectedWorkspace,
  )

  await page.goto('/not-a-real-route')
  await expect(page).toHaveURL(/\/404$/)
  await expect(page.getByRole('heading', { name: 'Page not found' })).toBeVisible()

  await page.setViewportSize({ width: 390, height: 844 })
  await page.goto('/home')
  await page.getByRole('button', { name: 'Open navigation' }).click()
  await expect(page.getByRole('dialog')).toBeVisible()
  await page.keyboard.press('Escape')

  await page.getByRole('button', { name: 'Open account menu' }).click()
  await page.getByRole('menuitem', { name: 'Log out' }).click()
  await expect(page).toHaveURL(/\/login$/)
  await page.goto('/home')
  await expect(page.getByRole('heading', { name: 'Sign in to OpsPilot' })).toBeVisible()
  expect(new URL(page.url()).searchParams.get('redirect')).toBe('/home')
  expect(errors).toEqual([])
})
