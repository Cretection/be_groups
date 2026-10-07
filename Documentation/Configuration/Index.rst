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
        *   The form no longer offers the kind "Classic", except for groups
            that already are classic. New groups start as "Role".
        *   No new classic groups can be created and no group can be changed
            to the kind "Classic" – neither in the form nor through the
            DataHandler API. A group created through the API without a kind
            is rejected, because its default kind would be "Classic".
        *   Backend users can only get roles assigned.
        *   The consistency check (planned, see :ref:`developer-planned`)
            reports remaining classic groups as an error.
        *   Existing classic groups and their assignments keep working. No
            permissions are deleted.

    The option takes effect immediately; there is no need to flush caches.

    ..  tip::

        Switch this option off as soon as all groups have been converted to
        building blocks and roles. From then on, the clean model is enforced.
