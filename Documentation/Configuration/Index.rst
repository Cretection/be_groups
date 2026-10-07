..  include:: /Includes.rst.txt

..  _configuration:

=============
Configuration
=============

The extension is configured in :guilabel:`Admin Tools > Settings >
Extension Configuration > be_groups`.

..  _configuration-allowClassicGroups:

Allow classic groups
====================

..  confval:: allowClassicGroups
    :name: be-groups-allowClassicGroups
    :type: boolean
    :default: true

    Allows backend groups of kind "Classic".

    Enabled (default)
        Classic groups can be created and assigned to backend users, next to
        roles. This is necessary during the migration of an existing
        installation.

    Disabled
        *   No new classic groups can be created and no group can be changed
            to the kind "Classic".
        *   Backend users can only get roles.
        *   The consistency check (planned, see :ref:`developer-planned`)
            reports remaining classic groups as an error.
        *   Existing classic groups and their assignments keep working. No
            permissions are deleted.

    ..  tip::

        Switch this option off as soon as all groups have been converted to
        building blocks and roles. From then on, the clean model is enforced.
