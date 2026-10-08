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

import { test as base, expect, type FrameLocator, type Locator, type Page } from '@playwright/test';
import { AxeBuilder } from '@axe-core/playwright';
import config from '../config.ts';

/**
 * The backend with the module in its content frame, after "backend-page.ts" of the TYPO3 Core.
 */
export class Backend {
  readonly contentFrame: FrameLocator;
  private readonly page: Page;

  constructor(page: Page) {
    this.page = page;
    this.contentFrame = page.frameLocator('#typo3-contentIframe');
  }

  /**
   * Opens a module by its path below /typo3/, e.g. "module/users/roles".
   */
  async open(path: string): Promise<void> {
    await this.page.goto(path);
    await expect(this.contentFrame.locator('body')).toBeVisible();
  }

  /**
   * Opens the form of a group or user through its link in the module "Roles & Building Blocks".
   */
  async openForm(linkText: string): Promise<void> {
    await this.open('module/users/roles');
    await this.contentFrame.getByRole('link', { name: linkText, exact: true }).first().click();
    await expect(this.contentFrame.locator('form[name="editform"]')).toBeVisible();
  }

  /**
   * A field of the open form by its column name, e.g. "subgroup".
   */
  field(column: string): Locator {
    return this.contentFrame.locator(`[name$="[${column}]"], [data-formengine-input-name$="[${column}]"]`);
  }

  /**
   * Saves the open form. Changing the groups of a user asks for the password (sudo mode of the
   * Core); changing a group does not, as the login of the test run counts as verification.
   */
  async save(): Promise<void> {
    const saved = this.page.locator('typo3-notification-message').filter({ hasText: 'Record saved' });
    const sudoMode = this.page.getByRole('dialog', { name: 'Verify with user password' });
    await this.contentFrame.locator('button[name="_savedok"]').click();
    await expect(saved.or(sudoMode).first()).toBeVisible();
    if (await sudoMode.isVisible()) {
      await sudoMode.getByLabel('Password').fill(config.login.admin.password);
      await sudoMode.getByRole('button', { name: 'Verify', exact: true }).click();
    }
    await expect(saved).toBeVisible();
    await expect(this.contentFrame.locator('form[name="editform"]')).toBeVisible();
  }

  /**
   * Checks the content frame against WCAG 2.2 AA. The shell of the backend belongs to the Core.
   * Excluded: the headings of grouped checkboxes (e.g. file operations), which the Core renders
   * as role="tab" without a tab list and with a button inside (SelectCheckBoxElement, TYPO3 14.3).
   */
  async expectAccessible(): Promise<void> {
    // The admin follows the color scheme of the browser ("auto"): make sure the theme of the
    // project is shown, so that the dark run never checks the light theme
    const textColor = await this.contentFrame.locator('body').evaluate((body) => getComputedStyle(body).color);
    const channels = (textColor.match(/[\d.]+/g) ?? []).slice(0, 3).map(Number);
    const scale = channels.some((channel) => channel > 1) ? 255 : 1;
    const lightText = channels.reduce((sum, channel) => sum + channel / scale, 0) / channels.length > 0.5;
    expect(lightText, `text color ${textColor}`).toBe(base.info().project.use.colorScheme === 'dark');

    const results = await new AxeBuilder({ page: this.page })
      .include('#typo3-contentIframe')
      .exclude(['#typo3-contentIframe', '[id^="formengine-select-checkbox-"] > .panel-heading[role="tab"]'])
      .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'])
      .analyze();
    const violations = results.violations.map((violation) => ({
      rule: violation.id,
      impact: violation.impact,
      targets: violation.nodes.map((node) => node.target.join(' ')),
    }));
    expect(violations).toEqual([]);
  }
}

export const test = base.extend<{ backend: Backend }>({
  backend: async ({ page }, use) => {
    await use(new Backend(page));
  },
});

export { expect };
