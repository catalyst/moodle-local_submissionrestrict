![Build Status](https://github.com/catalyst/moodle-local_submissionrestict/actions/workflows/ci.yml/badge.svg?branch=MOODLE_39_STABLE)

# Submission restriction #

TODO

## Versions and branches ##

| Moodle Version    |  Branch                | 
|-------------------|------------------------|
| Moodle 3.9+       | MOODLE_39_STABLE       | 

## Features ##
                                                      
TODO


## Supported activities ##
TODO


## Installing via uploaded ZIP file ##

1. Log in to your Moodle site as an admin and go to _Site administration >
   Plugins > Install plugins_.
2. Upload the ZIP file with the plugin code. You should only be prompted to add
   extra details if your plugin type is not automatically detected.
3. Check the plugin validation report and finish the installation.

## Installing manually ##

The plugin can be also installed by putting the contents of this directory to

    {your/moodle/dirroot}/local/submissionrestict

Afterwards, log in to your Moodle site as an admin and go to Site administration >
Notifications to complete the installation.

Alternatively, you can run

    $ php admin/cli/upgrade.php

to complete the installation from the command line.

## Dev notes ##

Statements are implemented as subplugins. See one of the existing statements to figure out how to implement one that you require. 


# Crafted by Catalyst IT

This plugin was developed by Catalyst IT Australia:

https://www.catalyst-au.net/

![Catalyst IT](/pix/catalyst-logo.png?raw=true)

# Contributing and Support

Issues, and pull requests using github are welcome and encouraged!

https://github.com/catalyst/moodle-local_submissionrestict/issues

If you would like commercial support or would like to sponsor additional improvements
to this plugin please contact us:

https://www.catalyst-au.net/contact-us
