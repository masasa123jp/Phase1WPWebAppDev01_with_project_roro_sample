import { test, expect } from '@playwright/test';

test('管理ダッシュボードに KPI が表示される', async ({ page }) => {
  await page.goto('/admin/#/dashboard');
  await expect(page.getByText('月間アクティブユーザー')).toBeVisible();
});
