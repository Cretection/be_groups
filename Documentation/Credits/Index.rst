..  include:: /Includes.rst.txt

..  _credits:

=================
Credits & History
=================

..  _credits-inventor:

The idea and its inventor
=========================

be_groups was invented by **Michael Klapper**
(`michaelklapper on GitHub <https://github.com/michaelklapper>`__).
In 2012, while working at morphodo / AOE, he developed the idea of splitting backend user groups into
building blocks with exactly one purpose and composing them into
"META" groups – and implemented it: the group kinds, the META composition,
the restriction of backend users to META groups, update wizards and the
first documentation.

More than a decade later, the official TYPO3 documentation recommends
exactly this structure: system groups, access control list groups and role
groups that only aggregate other groups. Michael Klapper's concept from
2012 is the model TYPO3 recommends today – this relaunch turns it into real
group kinds for current TYPO3 versions.

Version 1.0 is a complete rewrite: the code is new. The idea, the name and
the first implementation of 2012 are Michael Klapper's.

The relaunch for TYPO3 14 happens with Michael Klapper's explicit consent.
Thank you, Michael!

..  _credits-timeline:

Timeline
========

2012
    Michael Klapper develops be_groups at morphodo / AOE: group kinds,
    META groups, update wizards and documentation.

2014
    Christian Zenker makes the file permissions editable and regroups the
    fields of the rights form.

2016 – 2017
    AOE maintains the extension for TYPO3 6.2, 7 LTS and 8 LTS
    (`AOEpeople/be_groups <https://github.com/AOEpeople/be_groups>`__).
    Contributions by Tomas Norre Mikkelsen, Stefan Rotsch, Dragan Tomic,
    Jonathan Klauck and Martin Tepper.

2018
    The AOE repository is marked as no longer actively maintained and later
    archived.

2022
    Jonathan Starck, who got to know the extension during an internship at
    AOE, revives it for TYPO3 11 (versions 0.0.1 – 0.0.9).

2026
    Relaunch for TYPO3 14.3 LTS by Jonathan Starck with Michael Klapper's
    consent: kinds aligned with the official TYPO3 guideline, roles in the
    core field :sql:`subgroup`, enforced rules and a quality standard on the
    level of the TYPO3 core.

..  _credits-contributors:

Contributors
============

Thank you to everybody who contributed to be_groups over the years:

*   `Michael Klapper <https://github.com/michaelklapper>`__ – original author
*   Christian Zenker
*   Tomas Norre Mikkelsen
*   Stefan Rotsch
*   Dragan Tomic
*   Jonathan Klauck
*   Martin Tepper
*   Jonathan Starck – maintainer since 2022

The complete history is preserved in the
`Git repository <https://github.com/Cretection/be_groups/commits/main>`__.
