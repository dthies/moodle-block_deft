# Deft response block #

Provides polling and chat that update live without reloading main activity

Teachers may configure a number of questions or prompts in the block
and change the visibilities and other conditions of the items. Comments
may also be enabled to conduct discussions.

An external service is available to enable updating the contents in users
browsers via the websocket protocol. The service is free for testing and
limited usage, but will be capped for larger sites without a service
agreement in the future. The service is linked as an External Tool
(LTI), but user information is not shared with the service other than
what is necessary to provide connection i.e IP number associated with
block's context.

The service will remain free for smaller sites, but sites with larger usage
may be require a service agreement after 60 day to prevent usage caps.

## Installing via uploaded ZIP file ##

1. Log in to your Moodle site as an admin and go to _Site administration >
   Plugins > Install plugins_.
2. Upload the ZIP file with the plugin code. You should only be prompted to add
   extra details if your plugin type is not automatically detected.
3. Check the plugin validation report and finish the installation.
4. Ensure Deft response block is enabled.
5. To enable live updating of block go to admin settings for Deft response. Check
   the enable updating box and save. Return to setting page and a button to activate
   the service should appear. Clicking the button should add deftly.us as an
   External tool. If this is successful then usage information will be displayed
   on the settings page. If not contact the developers for urls and instructions to
   configure manually.

## Installing manually ##

The plugin can be also installed by putting the contents of this directory to

    {your/moodle/dirroot}/blocks/deft

Afterwards, log in to your Moodle site as an admin and go to _Site administration >
Notifications_ to complete the installation.

Alternatively, you can run

    $ php admin/cli/upgrade.php

to complete the installation from the command line.

## License ##

2022 Daniel Thies <dethies@gmail.com>

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <https://www.gnu.org/licenses/>.
