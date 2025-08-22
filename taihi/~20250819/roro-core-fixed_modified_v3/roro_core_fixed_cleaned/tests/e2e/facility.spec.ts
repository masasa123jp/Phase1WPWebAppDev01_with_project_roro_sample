import { test, expect } from '@playwright/test';

test('facility list shows items', async ({ page }) => {
  await page.goto('/');
  await page.click('text=Facility List'); // assume nav link exists
  await expect(page.getByRole('listitem').first()).toBeVisible();
});
