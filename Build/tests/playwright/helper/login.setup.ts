/*
 * This file is part of the TYPO3 CMS extension "be_groups".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

import { test as setup, expect } from '@playwright/test';
import config from '../config.ts';

setup('log in as admin', async ({ page }) => {
  await page.goto(config.baseUrl);
  await page.getByLabel('Username').fill(config.login.admin.username);
  await page.getByLabel('Password').fill(config.login.admin.password);
  await page.getByRole('button', { name: 'Login' }).click();
  await expect(page.locator('typo3-backend-sidebar-toggle')).toBeVisible();
  await page.context().storageState({ path: `${import.meta.dirname}/../../../../.Build/logs/playwright-auth/admin.json` });
});
