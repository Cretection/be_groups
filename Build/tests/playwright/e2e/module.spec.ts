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

import type { Locator } from '@playwright/test';
import { test, expect } from '../fixtures/backend.ts';

// Links in the module are smaller than 24 px, so the centres of their lines must be at least
// 24 px apart (WCAG 2.2, success criterion 2.5.8). Every line a link wraps onto counts.
async function expectLinesOfLinksApart(container: Locator): Promise<void> {
  const lines = await container.getByRole('link').evaluateAll((links) => [...new Set(links.flatMap(
    (link) => [...link.getClientRects()].map((rect) => Math.round(rect.top + rect.height / 2)),
  ))].sort((a, b) => a - b));
  expect(lines.length, 'lines with links').toBeGreaterThan(1);
  for (let i = 1; i < lines.length; i++) {
    expect((lines[i] ?? 0) - (lines[i - 1] ?? 0), `centres of the lines: ${lines.join(', ')}`).toBeGreaterThanOrEqual(24);
  }
}

// The module "Roles & Building Blocks" with the scenario of "scenario.php"
test.describe('Module "Roles & Building Blocks"', () => {
  test.beforeEach(async ({ backend }) => {
    // The sorting and the kind filter are kept per user: start every test with the defaults
    await backend.open('module/users/roles?sorting=title&kind=');
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
    // The explanation is text, not a tooltip, so it reaches keyboard and touch users as well
    await expect(teamLead.getByText('TYPO3 still grants the permissions of this group')).toBeVisible();
  });

  test('filters the building blocks by kind', async ({ backend }) => {
    const frame = backend.contentFrame;
    const filter = frame.getByRole('group', { name: 'Building blocks of kind' });
    await filter.getByRole('link', { name: 'File mount' }).click();
    await expect(filter.getByRole('link', { name: 'File mount' })).toHaveAttribute('aria-current', 'true');
    // The selected kind is marked by an icon, not only by a slightly darker background
    await expect(filter.getByRole('link', { name: 'File mount' }).locator('[data-identifier="actions-check"]')).toBeVisible();
    await expect(filter.locator('[data-identifier="actions-check"]')).toHaveCount(1);
    await expect(frame.getByRole('heading', { level: 3 })).toHaveText([/File mount/]);
    await expect(frame.getByRole('table', { name: /File mount/ }).getByRole('rowheader')).toHaveText([/Documents/, /Images/]);
  });

  test('sorts by usage', async ({ backend }) => {
    const sorting = backend.contentFrame.getByRole('group', { name: 'Sort by' });
    await sorting.getByRole('link', { name: 'Usage' }).click();
    await expect(sorting.getByRole('link', { name: 'Usage' })).toHaveAttribute('aria-current', 'true');
    await expect(sorting.getByRole('link', { name: 'Title' })).toHaveAttribute('aria-current', 'false');
    await expect(sorting.getByRole('link', { name: 'Usage' }).locator('[data-identifier="actions-check"]')).toBeVisible();
    await expect(sorting.getByRole('link', { name: 'Title' }).locator('[data-identifier="actions-check"]')).toHaveCount(0);
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

  test('creates a building block of the chosen kind', async ({ backend }) => {
    const docHeader = backend.contentFrame.locator('.module-docheader');
    await docHeader.getByRole('button', { name: 'New building block' }).click();
    await docHeader.getByRole('link', { name: 'File mount' }).click();
    // The form of the kind opens right away, without changing the kind (which saves the record)
    await expect(backend.contentFrame.getByRole('heading', { level: 1 })).toHaveText(/File mount/);
    await expect(backend.field('file_mountpoints').first()).toBeAttached();
    await expect(backend.field('groupMods')).toHaveCount(0);
  });

  test('keeps the lines of the rows of roles 24 px apart', async ({ backend }) => {
    const frame = backend.contentFrame;
    for (const role of ['Content manager', 'Team lead']) {
      await expectLinesOfLinksApart(frame.getByRole('row').filter({ has: frame.getByRole('rowheader', { name: role }) }).getByRole('cell').first());
    }
  });

  test('keeps wrapped lists of groups 24 px apart', async ({ backend, page }) => {
    await page.setViewportSize({ width: 480, height: 900 });
    const frame = backend.contentFrame;
    const contentEditing = frame.getByRole('table', { name: /Access rights/ }).getByRole('row').filter({ has: frame.getByRole('rowheader', { name: 'Content editing' }) });
    // "Used in": META: Content manager, META: Marketing
    await expectLinesOfLinksApart(contentEditing.getByRole('cell').first());
  });

  test('keeps the classic groups 24 px apart', async ({ backend }) => {
    const items = backend.contentFrame.getByRole('heading', { name: /Classic groups/ }).locator('xpath=following-sibling::ul[1]/li');
    // The scenario has one classic group, so the space each item takes up in the list counts
    const pitches = await items.evaluateAll((elements) => elements.map((item) => {
      const gap = parseFloat(getComputedStyle(item.parentElement ?? item).rowGap);
      return item.getBoundingClientRect().height + Math.max(parseFloat(getComputedStyle(item).marginBottom), Number.isNaN(gap) ? 0 : gap);
    }));
    expect(pitches.length).toBeGreaterThan(0);
    for (const pitch of pitches) {
      expect(pitch).toBeGreaterThanOrEqual(24);
    }
  });

  test('aligns the cells of tall rows at the top', async ({ backend }) => {
    const frame = backend.contentFrame;
    const tables = [
      frame.getByRole('table', { name: 'Roles with their building blocks and users' }),
      frame.getByRole('table', { name: /Building blocks of kind "Access rights"/ }),
    ];
    for (const table of tables) {
      await expect(table.getByRole('rowheader').first()).toHaveCSS('vertical-align', 'top');
      await expect(table.getByRole('cell').first()).toHaveCSS('vertical-align', 'top');
    }
  });

  test('meets WCAG 2.2 AA @a11y', async ({ backend }) => {
    await backend.expectAccessible();
  });

  test('meets WCAG 2.2 AA with the kinds of new building blocks open @a11y', async ({ backend }) => {
    const docHeader = backend.contentFrame.locator('.module-docheader');
    await docHeader.getByRole('button', { name: 'New building block' }).click();
    await expect(docHeader.getByRole('link', { name: 'File mount' })).toBeVisible();
    await backend.expectAccessible();
  });
});
