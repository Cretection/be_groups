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

// The instance of "Build/Scripts/setupE2E.sh"; "runTests.sh -s e2e" sets the URL
export default {
  // The trailing slash keeps relative URLs like page.goto('module/users/roles') below /typo3/
  baseUrl: process.env.PLAYWRIGHT_BASE_URL || 'http://web:80/typo3/',
  login: {
    admin: {
      username: process.env.E2E_ADMIN_USERNAME || 'admin',
      password: process.env.E2E_ADMIN_PASSWORD || 'E2e-Password-1',
    },
  },
};
