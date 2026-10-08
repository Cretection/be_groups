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

// Each building block shows the settings of its kind only (rule R1), roles list their possible
// members grouped by kind.
const buildingBlocks = [
  { title: 'Content editing', heading: 'ACL: Content editing', field: 'groupMods' },
  { title: 'Marketing pages', heading: 'PG: Marketing pages', field: null },
  { title: 'Website', heading: 'DBM: Website', field: 'db_mountpoints' },
  { title: 'Images', heading: 'FM: Images', field: 'file_mountpoints' },
  { title: 'Upload and edit files', heading: 'FO: Upload and edit files', field: 'file_permissions' },
  { title: 'Products', heading: 'CM: Products', field: 'category_perms' },
  { title: 'English', heading: 'L: English', field: 'allowed_languages' },
  { title: 'Clear page cache', heading: 'TS: Clear page cache', field: 'TSconfig' },
  { title: 'Drafts', heading: 'WS: Drafts', field: 'workspace_perms' },
];
const permissionFields = [...buildingBlocks.flatMap(({ field }) => (field === null ? [] : [field])), 'subgroup'];

test.describe('Forms of groups', () => {
  for (const { title, heading, field } of buildingBlocks) {
    test(`shows only the settings of its kind for "${heading}"`, async ({ backend }) => {
      await backend.openForm(title);
      await expect(backend.contentFrame.getByRole('heading', { level: 1 })).toHaveText(heading);
      for (const permissionField of permissionFields) {
        if (permissionField === field) {
          await expect(backend.field(permissionField).first()).toBeAttached();
        } else {
          await expect(backend.field(permissionField)).toHaveCount(0);
        }
      }
    });
  }

  test('lists the possible members of a role grouped by kind', async ({ backend }) => {
    await backend.openForm('Content manager');
    const available = backend.contentFrame.locator('select.t3js-formengine-select-itemstoselect[data-relatedfieldname$="[subgroup]"]');
    await expect(available.locator('optgroup').first()).toBeAttached();
    const groups = await available.locator('optgroup').evaluateAll((optgroups) => optgroups.map((optgroup) => optgroup.getAttribute('label')));
    expect(groups).toEqual([
      'Access rights',
      'Page permission group',
      'Page tree mount',
      'File mount',
      'File operations',
      'Category mount',
      'Languages',
      'TSconfig',
      'Workspace',
      'Roles (cannot be added to a role)',
      'Classic groups (cannot be added to a role)',
    ]);
    // The stored order of the building blocks is their TSconfig precedence
    await expect(backend.contentFrame.locator('select[multiple][data-formengine-input-name$="[subgroup]"] option')).toHaveText([
      /ACL: Content editing/,
      /ACL: Page properties/,
      /ACL: Media/,
      /DBM: Website/,
      /FM: Images/,
      /FM: Documents/,
      /FO: Upload and edit files/,
      /L: English/,
      /TS: Clear page cache/,
      /TS: Editor defaults/,
    ]);
  });

  test('meets WCAG 2.2 AA in the form of a role @a11y', async ({ backend }) => {
    await backend.openForm('Content manager');
    await backend.expectAccessible();
  });

  test('meets WCAG 2.2 AA in the form of a building block @a11y', async ({ backend }) => {
    await backend.openForm('Upload and edit files');
    await backend.expectAccessible();
  });
});
