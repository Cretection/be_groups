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

import { test, expect } from '../fixtures/backend.ts';

// The Core module "Users" shows the kind as prefix of every group (template overrides)
test.describe('Core module "Users"', () => {
  test('shows the kind of the groups of a user', async ({ backend }) => {
    await backend.open('module/users/management');
    const anna = backend.contentFrame.getByRole('row').filter({ hasText: '(anna)' });
    await expect(anna).toContainText('META: Content manager');
  });

  test('shows the kind of the groups and their subgroups', async ({ backend }) => {
    await backend.open('module/users/management/BackendUser/groups');
    const teamLead = backend.contentFrame.getByRole('row').filter({ hasText: 'META: Team lead' });
    await expect(teamLead).toContainText('ACL: Page properties, TS: Clear page cache, META: Content manager');
  });

  test('meets WCAG 2.2 AA in the list of users @a11y', async ({ backend }) => {
    await backend.open('module/users/management');
    await backend.expectAccessible();
  });

  test('meets WCAG 2.2 AA in the list of groups @a11y', async ({ backend }) => {
    await backend.open('module/users/management/BackendUser/groups');
    await backend.expectAccessible();
  });
});
