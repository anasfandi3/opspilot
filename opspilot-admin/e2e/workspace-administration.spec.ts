import { expect, test, type Page } from '@playwright/test'

async function login(page: Page, email: string) {
  await page.goto('/login')
  await page.getByLabel('Email').fill(email)
  await page.getByLabel('Password').fill('password')
  await page.getByRole('button', { name: 'Sign in' }).click()
  await expect(page).toHaveURL(/\/home/)
}

async function selectDemoWorkspace(page: Page) {
  const switcher = page.getByRole('button', { name: 'Switch workspace' })
  if ((await switcher.innerText()).includes('Acme Operations')) return
  await switcher.click()
  await page.getByRole('menuitem', { name: /Acme Operations/ }).click()
  await expect(switcher).toContainText('Acme Operations')
}

function assertCleanConsole(page: Page) {
  const errors: string[] = []
  page.on('console', (message) => {
    const expectedGuestCheck =
      message.location().url.includes('/api/v1/me') && message.text().includes('401')
    if ((message.type() === 'error' || message.type() === 'warning') && !expectedGuestCheck)
      errors.push(message.text())
  })
  page.on('pageerror', (error) => errors.push(error.message))
  return () => expect(errors).toEqual([])
}

test.describe('Patch 2 workspace administration', () => {
  test('owner inspects members and manages a disposable invitation', async ({ page }) => {
    const verifyConsole = assertCleanConsole(page)
    await login(page, 'owner@opspilot.test')
    await selectDemoWorkspace(page)

    await page.goto('/settings/workspace')
    await expect(page.getByRole('heading', { name: 'Workspace settings' })).toBeVisible()
    await expect(page.getByLabel('Workspace name')).toHaveValue(/.+/)

    await page.goto('/settings/members')
    await expect(page.getByRole('heading', { name: 'Members' })).toBeVisible()
    await expect(page.getByText('requester@opspilot.test')).toBeVisible()
    const requesterRow = page.getByRole('row').filter({ hasText: 'requester@opspilot.test' })
    await requesterRow.getByRole('button', { name: 'Edit role' }).click()
    await expect(page.getByRole('heading', { name: 'Edit member role' })).toBeVisible()
    await expect(page.getByRole('combobox')).toContainText('Requester')
    await page.keyboard.press('Escape')

    await page.goto('/settings/invitations')
    const email = `patch2-${Date.now()}-${test.info().project.name}@example.test`
    await page.getByRole('button', { name: 'Invite member' }).click()
    await page.getByLabel('Email').fill(email)
    await page.getByRole('button', { name: 'Send invitation' }).click()
    await expect(page.getByText(email)).toBeVisible()
    const invitationRow = page.getByRole('row').filter({ hasText: email })
    await invitationRow.getByRole('button', { name: 'Resend' }).click()
    await expect(page.getByText('Invitation resent')).toBeVisible()
    await invitationRow.getByRole('button', { name: 'Revoke' }).click()
    await page.getByRole('button', { name: 'Revoke invitation' }).click()
    await expect(invitationRow.getByText('Revoked')).toBeVisible()
    verifyConsole()
  })

  test('lower-permission user cannot access administration routes', async ({ page }) => {
    const verifyConsole = assertCleanConsole(page)
    await login(page, 'requester@opspilot.test')
    await expect(page.getByRole('link', { name: 'Members' })).toBeVisible()
    await expect(page.getByRole('link', { name: 'Invitations' })).toHaveCount(0)
    await page.goto('/settings/members')
    await expect(page.getByRole('heading', { name: 'Members' })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Edit role' })).toHaveCount(0)
    await page.goto('/settings/invitations')
    await expect(page).toHaveURL(/\/403$/)
    verifyConsole()
  })

  test('mobile settings navigation and drawers remain usable', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 })
    await login(page, 'owner@opspilot.test')
    await selectDemoWorkspace(page)
    await page.getByRole('button', { name: 'Open navigation' }).click()
    await page.getByRole('link', { name: 'Invitations' }).click()
    await page.getByRole('button', { name: 'Invite member' }).click()
    await expect(page.getByRole('heading', { name: 'Invite member' })).toBeVisible()
    await expect(page.getByLabel('Email')).toBeInViewport()
  })
})
