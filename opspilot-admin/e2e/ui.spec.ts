import { expect, test } from '@playwright/test'

test('UI playground shell and query interactions work', async ({ page }) => {
  const errors: string[] = []
  page.on('console', (message) => message.type() === 'error' && errors.push(message.text()))
  await page.goto('/ui')
  await expect(page.getByRole('heading', { name: 'UI foundation' })).toBeVisible()
  await page.getByTestId('sidebar-toggle').click()
  await expect(page.getByTestId('sidebar-toggle')).toHaveAttribute('aria-label', 'Expand sidebar')
  await page.getByLabel('Go to page 3').click()
  await expect(page).toHaveURL(/page=3/)
  await page.getByLabel('Search requests').fill('purchase')
  await expect(page).toHaveURL(/search=purchase/)
  await page.getByTestId('table-scroll').getByRole('button', { name: 'Request' }).click()
  await expect(page).toHaveURL(/sort=title/)
  await page.getByLabel('Select row').first().click()
  await expect(page.getByText('1 selected')).toBeVisible()
  await page.getByRole('button', { name: 'Next' }).click()
  await expect(page).toHaveURL(/page=2/)
  await page.goBack()
  await expect(page).toHaveURL(/sort=title/)
  expect(errors).toEqual([])
})

test('themes, overlays, date, file, and toasts are interactive', async ({ page }) => {
  await page.goto('/ui')
  await page.getByTestId('theme-toggle').click()
  await page.getByRole('menuitem', { name: /Dark/ }).click()
  await expect(page.locator('html')).toHaveClass(/dark/)
  await page.reload()
  await expect(page.locator('html')).toHaveClass(/dark/)
  await page.getByRole('button', { name: 'Open dialog' }).click()
  await expect(page.getByRole('dialog', { name: 'Focused interaction' })).toBeVisible()
  await page.keyboard.press('Escape')
  await page.getByRole('button', { name: 'Open drawer' }).click()
  await expect(page.getByRole('dialog', { name: 'Edit details' })).toBeVisible()
  await page.keyboard.press('Escape')
  await page.getByLabel('Due date').click()
  await expect(page.getByRole('application')).toBeVisible()
  await page.keyboard.press('Escape')
  await page.locator('input[type=file]').setInputFiles({
    name: 'evidence.pdf',
    mimeType: 'application/pdf',
    buffer: Buffer.from('demo'),
  })
  await expect(page.getByText('evidence.pdf')).toBeVisible()
  await page.getByRole('button', { name: 'Success toast' }).click()
  await expect(page.getByText('Changes saved successfully')).toBeVisible()
})

test('mobile navigation stays in a sheet', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 })
  await page.goto('/ui')
  await page.getByTestId('mobile-menu').click()
  await expect(page.getByRole('dialog')).toBeVisible()
  await page.keyboard.press('Escape')
  await expect(page.getByRole('dialog')).toBeHidden()
  const scroll = page.getByTestId('table-scroll')
  await expect(scroll).toBeVisible()
  expect(await scroll.evaluate((element) => element.scrollWidth > element.clientWidth)).toBe(true)
})
