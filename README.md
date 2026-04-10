[![ci](https://github.com/catalyst/moodle-local_submissionrestrict/actions/workflows/ci.yml/badge.svg?branch=MOODLE_405_STABLE)](https://github.com/catalyst/moodle-local_submissionrestrict/actions/workflows/ci.yml?branch=MOODLE_405_STABLE)

# Submission restriction #

A Moodle local plugin that enforces standardised submission due dates for activities across a Moodle site. Administrators configure a list of permitted timeslots; teachers must select from these timeslots or provide an approved reason when setting a non-standard deadline. When a non-standard deadline is used, a configurable notification banner is shown to students on the activity page explaining the reason.

## Versions and branches ##

| Moodle Version          | Branch            |
|-------------------------|-------------------|
| Moodle 4.5              | MOODLE_405_STABLE |
| Moodle 3.9 - Moodle 4.1 | MOODLE_39_STABLE  |

## Features ##

* Configure a site-wide list of permitted due date timeslots (e.g. 11:55 PM, 5:00 PM).
* Require teachers to select from the permitted timeslots when creating or editing a supported activity.
* Allow authorised users to override the standard timeslots by selecting a pre-configured reason for variation.
* Optionally attach a student-facing description to each reason; when set, a notification banner is displayed on the activity page informing students of the non-standard deadline and its reason.
* Submission overrides report accessible from the category navigation, listing all activities with non-standard deadlines along with their reasons.
* Optionally reset due dates to a configured default time when a supported activity is restored from backup.
* Optionally recalculate penalty cut-off dates when a due date is changed.
* Integration with the [report_editdates](https://moodle.org/plugins/report_editdates) plugin for bulk date editing.

## Supported activities ##

* Assignment (`mod_assign`)
* Quiz (`mod_quiz`)

## Installing via uploaded ZIP file ##

1. Log in to your Moodle site as an admin and go to _Site administration >
   Plugins > Install plugins_.
2. Upload the ZIP file with the plugin code. You should only be prompted to add
   extra details if your plugin type is not automatically detected.
3. Check the plugin validation report and finish the installation.

## Installing manually ##

The plugin can be also installed by putting the contents of this directory to

    {your/moodle/dirroot}/local/submissionrestrict

Afterwards, log in to your Moodle site as an admin and go to _Site administration >
Notifications_ to complete the installation.

Alternatively, you can run

    $ php admin/cli/upgrade.php

to complete the installation from the command line.

## Configuration ##

After installation, navigate to _Site administration > Local plugins > Submission restrictions_ to:

* Define the list of permitted timeslots (one per line).
* Define the reasons for variation with optional student-facing descriptions using the `::` separator.
* Configure the default restore time and whether due dates should be reset after restore.
* Set the default value for the "Recalculate penalty" field.

## Dev notes ##

Support for additional activity types is implemented by adding a new class under `classes/local/mod/` that extends `mod_base`. See the existing `assign.php` and `quiz.php` implementations as a reference.

## Warm thanks ##

Thanks to Monash University (https://www.monash.edu) for funding the development of this plugin.

# Crafted by Catalyst IT

This plugin was developed by Catalyst IT Australia:

https://www.catalyst-au.net/

![Catalyst IT](/pix/catalyst-logo.png?raw=true)

# Contributing and Support

Issues, and pull requests using github are welcome and encouraged!

https://github.com/catalyst/moodle-local_submissionrestrict/issues

If you would like commercial support or would like to sponsor additional improvements
to this plugin please contact us:

https://www.catalyst-au.net/contact-us
