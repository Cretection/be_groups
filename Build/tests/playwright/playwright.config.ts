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

import { defineConfig } from '@playwright/test';
import config from './config.ts';

// End-to-end tests of be_groups (CODING_GUIDELINES.md, §13), after the configuration of the
// TYPO3 Core. Every test runs in the light theme; the accessibility checks (tag "@a11y") also
// run in the dark theme. The admin follows the color scheme of the browser ("auto").
const reports = `${import.meta.dirname}/../../../.Build/logs`;
const storageState = `${reports}/playwright-auth/admin.json`;

export default defineConfig({
  testDir: '.',
  timeout: 60 * 1000,
  expect: {
    timeout: 10 * 1000,
  },
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: 0,
  // One instance with one SQLite database and one admin
  workers: 1,
  reporter: [
    ['list'],
    ['html', { outputFolder: `${reports}/playwright-report`, open: 'never' }],
  ],
  use: {
    baseURL: config.baseUrl,
    viewport: { width: 1440, height: 900 },
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [
    {
      name: 'login',
      testMatch: 'helper/login.setup.ts',
      use: { storageState: undefined },
    },
    {
      name: 'light',
      testMatch: 'e2e/**/*.spec.ts',
      dependencies: ['login'],
      use: { colorScheme: 'light', storageState },
    },
    {
      name: 'dark',
      testMatch: 'e2e/**/*.spec.ts',
      grep: /@a11y/,
      dependencies: ['login'],
      use: { colorScheme: 'dark', storageState },
    },
  ],
  outputDir: `${reports}/playwright-results`,
});
