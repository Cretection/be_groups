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

// The rules reject new relations that break the role model and say which groups they rejected.
// Titles in the messages are isolated (U+2068 ... U+2069) against bidirectional formatting characters.
test.describe('Rules when saving', () => {
  test('rejects a role as member of a role', async ({ backend }) => {
    await backend.openForm('Marketing');
    const available = backend.contentFrame.locator('select.t3js-formengine-select-itemstoselect[data-relatedfieldname$="[subgroup]"]');
    await available.locator('option', { hasText: 'META: Content manager' }).click();
    await backend.save();
    await expect(backend.contentFrame.getByText('Groups not added')).toBeVisible();
    await expect(backend.contentFrame.getByText(/These groups have not been added: \u2068META: Content manager\u2069 \[\d+\]/)).toBeVisible();
    await expect(backend.contentFrame.locator('select[multiple][data-formengine-input-name$="[subgroup]"] option').filter({ hasText: 'META: Content manager' })).toHaveCount(0);
  });

  test('rejects a building block assigned to a user directly', async ({ backend }) => {
    await backend.openForm('anna');
    const available = backend.contentFrame.locator('select.t3js-formengine-select-itemstoselect[data-relatedfieldname$="[usergroup]"]');
    await available.locator('option', { hasText: 'DBM: Website' }).click();
    await backend.save();
    await expect(backend.contentFrame.getByText('Groups not assigned')).toBeVisible();
    await expect(backend.contentFrame.getByText(/These groups have not been assigned: \u2068DBM: Website\u2069 \[\d+\]/)).toBeVisible();
    await expect(backend.contentFrame.locator('select[multiple][data-formengine-input-name$="[usergroup]"] option')).toHaveText([/META: Content manager/]);
  });
});
