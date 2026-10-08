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

// The module "Roles & Building Blocks" with the scenario of "scenario.php"
test.describe('Module "Roles & Building Blocks"', () => {
  test.beforeEach(async ({ backend }) => {
    await backend.open('module/users/roles');
  });

  test('lists the roles with their building blocks and users', async ({ backend }) => {
    const roles = backend.contentFrame.getByRole('table', { name: 'Roles with their building blocks and users' });
    await expect(roles.getByRole('rowheader')).toHaveText([/Content manager/, /Marketing/, /Team lead/]);
    const contentManager = roles.getByRole('row').filter({ has: backend.contentFrame.getByRole('rowheader', { name: 'Content manager' }) });
    await expect(contentManager).toContainText('Access rights: Content editing, Media, Page properties');
    await expect(contentManager).toContainText('File mount: Documents, Images');
    await expect(contentManager.getByRole('link', { name: 'anna' })).toBeVisible();
  });

  test('shows the TSconfig precedence of a role with several TSconfig building blocks', async ({ backend }) => {
    const frame = backend.contentFrame;
    const contentManager = frame.getByRole('row').filter({ has: frame.getByRole('rowheader', { name: 'Content manager' }) });
    await expect(contentManager).toContainText('TSconfig precedence (later building blocks override earlier ones)');
    await expect(contentManager.locator('ol > li')).toHaveText(['Clear page cache', 'Editor defaults']);
    // Marketing has no TSconfig building block, so the order does not matter there
    const marketing = frame.getByRole('row').filter({ has: frame.getByRole('rowheader', { name: 'Marketing', exact: true }) });
    await expect(marketing).not.toContainText('TSconfig precedence');
  });

  test('marks a role that contains a role', async ({ backend }) => {
    const frame = backend.contentFrame;
    await expect(frame.getByText('Inconsistencies found')).toBeVisible();
    const teamLead = frame.getByRole('row').filter({ has: frame.getByRole('rowheader', { name: 'Team lead' }) });
    await expect(teamLead).toContainText('not a building block');
    await expect(teamLead.getByRole('link', { name: 'META: Content manager' })).toBeVisible();
  });

  test('filters the building blocks by kind', async ({ backend }) => {
    const frame = backend.contentFrame;
    const filter = frame.getByRole('group', { name: 'Building blocks of kind' });
    await filter.getByRole('link', { name: 'File mount' }).click();
    await expect(filter.getByRole('link', { name: 'File mount' })).toHaveAttribute('aria-current', 'true');
    await expect(frame.getByRole('heading', { level: 3 })).toHaveText([/File mount/]);
    await expect(frame.getByRole('table', { name: /File mount/ }).getByRole('rowheader')).toHaveText([/Documents/, /Images/]);
  });

  test('sorts by usage', async ({ backend }) => {
    const sorting = backend.contentFrame.getByRole('group', { name: 'Sort by' });
    await sorting.getByRole('link', { name: 'Usage' }).click();
    await expect(sorting.getByRole('link', { name: 'Usage' })).toHaveAttribute('aria-current', 'true');
    await expect(sorting.getByRole('link', { name: 'Title' })).toHaveAttribute('aria-current', 'false');
  });

  test('lists classic groups separately', async ({ backend }) => {
    const frame = backend.contentFrame;
    await expect(frame.getByRole('heading', { name: /Classic groups/ })).toBeVisible();
    await expect(frame.getByRole('link', { name: 'Legacy newsletter' })).toBeVisible();
  });

  test('opens the form of a role', async ({ backend }) => {
    const frame = backend.contentFrame;
    await frame.getByRole('rowheader', { name: 'Content manager' }).getByRole('link').click();
    await expect(frame.getByRole('heading', { level: 1 })).toHaveText('META: Content manager');
  });

  test('meets WCAG 2.2 AA @a11y', async ({ backend }) => {
    await backend.expectAccessible();
  });
});
