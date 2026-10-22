..  include:: /Includes.rst.txt

..  _configuration:

=============
Configuration
=============

System maintainers configure the extension in :guilabel:`System > Settings >
Extension Configuration > be_groups`; TYPO3 asks for their password first.
The option is on the tab :guilabel:`Basic` under
:guilabel:`Enable features`.

..  _configuration-allowClassicGroups:

Allow classic groups
====================

..  confval:: allowClassicGroups
    :name: be-groups-allowClassicGroups
    :type: boolean
    :default: true

    Allows classic groups: backend groups of kind "Classic (all settings)".

    Enabled (default)
        Classic groups can be created and assigned to backend users, next to
        roles. This is necessary during the migration of an existing
        installation.

    Disabled
        *   Neither the form nor the wizard :guilabel:`Create new record`
            offers the kind "Classic (all settings)" any more, except the
            form of groups that already are classic. New groups start as
            "Role".
        *   No new classic groups can be created and no group can become
            classic – neither in the form nor through the DataHandler API. A
            group created through the API without a kind is rejected,
            because it would be classic by default.
        *   Backend users can only get roles assigned. The form of a user
            lists the classic groups under "Classic groups (disabled, cannot
            be assigned)".
        *   The consistency check ``begroups:audit`` reports remaining
            classic groups as error ``classic-group-disabled``, see
            :ref:`usage-audit`.
        *   Existing classic groups and their assignments keep working. No
            permissions are deleted.

    The option takes effect immediately; there is no need to flush caches.

    ..  tip::

        Switch this option off as soon as all groups have been converted to
        building blocks and roles. From then on, the clean model is enforced.
