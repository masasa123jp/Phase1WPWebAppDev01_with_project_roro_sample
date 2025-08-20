import { test, expect } from '@playwright/test';

test('ガチャを回して賞が表示される', async ({ page }) => {
  await page.goto('/report');
  await page.click('text=ガチャ');
  await page.click('text=Spin');
  const modal = page.locator('[role=dialog]');
  await expect(modal).toBeVisible();
  await expect(modal).toContainText(/おめでとう/);
});
