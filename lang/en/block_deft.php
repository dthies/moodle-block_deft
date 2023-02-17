<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin strings are defined here.
 *
 * @package     block_deft
 * @category    string
 * @copyright   2022 Daniel Thies <dethies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['activate'] = 'Activate';
$string['activatemessage'] = 'This will contact the server and deftly.us and obtain and registration url to configure an LTI connection. It will then a External tool configuration to you site. This will be used to authenticate messages sent to clients to update the block content.';
$string['addchoice'] = 'Add choice';
$string['addcomments'] = 'Add comments';
$string['addtext'] = 'Add text';
$string['addvenue'] = 'Add venue';
$string['authorizationreceived'] = 'Authorization received';
$string['autogaincontrol'] = 'Auto gain control';
$string['autogaincontrol_desc'] = 'Automatically adjusts volume to standard level. This may help understand speech, but may amplify some noise and reduce dynamic changes in music.';
$string['cachedef_comments'] = 'Deft choice comments';
$string['cachedef_results'] = 'Deft choice results';
$string['cachedef_tasks'] = 'Deft tasks';
$string['cachedef_tokens'] = 'Deft service auth tokens';
$string['changestatus'] = 'Change status';
$string['charttype'] = 'Display results in pie graph';
$string['close'] = 'Close';
$string['closevenue'] = 'Close venue';
$string['configquestion'] = 'Question';
$string['configtitle'] = 'Deft response block title';
$string['configuremanually'] = 'The automatic registration process has failed for some reason. The LTI tool must be configured manually. Contact the developers to report this and obtain the urls for manual configuration.';
$string['confirmdelete'] = 'Confirm delete \'{$a}\'?';
$string['connections'] = '# of connections';
$string['content'] = 'Content';
$string['count'] = 'Count';
$string['cumulative'] = 'Cumulative';
$string['current'] = 'Current';
$string['deft:addinstance'] = 'Add instance';
$string['deft:myaddinstance'] = 'Add instance to dashboard';
$string['deft:edit'] = 'Edit tasks';
$string['deft:choose'] = 'Make choice';
$string['deft:joinvenue'] = 'Join venue';
$string['deft:manage'] = 'Manage tasks';
$string['deft:moderate'] = 'Moderate venue';
$string['echocancellation'] = 'Echo cancellation';
$string['echocancellation_desc'] = 'Attempt to remove echo from background.';
$string['editchoice'] = 'Edit choice';
$string['editcomments'] = 'Edit comments';
$string['edittext'] = 'Edit text';
$string['editvenue'] = 'Edit venue';
$string['enableservicemessage'] = 'Updating through the deftly.us is disabled.  Enable the setting above and then activate the service to enable live updates to the block\'s content.';
$string['enableupdating_desc'] = 'Enable updating';
$string['enableupdating'] = 'Enable updating';
$string['expandcomments'] = 'Expand comments';
$string['eventchoicesubmitted'] = 'Choice submitted';
$string['eventhandlowersent'] = 'Hand lower sent';
$string['eventhandraisesent'] = 'Hand raise sent';
$string['eventmuteswitched'] = 'Mute switched';
$string['eventtaskcreated'] = 'Task created';
$string['eventtaskdeleted'] = 'Task deleted';
$string['eventtaskupdated'] = 'Task updated';
$string['eventvenueended'] = 'Venue ended';
$string['eventvenuestarted'] = 'Venue started';
$string['guests'] = 'Guests ({$a})';
$string['handloweredsent'] = 'Hand lowered';
$string['handraisesent'] = 'Hand raised';
$string['join'] = 'Join';
$string['label'] = 'Label';
$string['leave'] = 'Leave';
$string['limit'] = 'Limit';
$string['lowerhand'] = 'Lower hand';
$string['manage'] = 'Manage';
$string['managetasks'] = 'Manage tasks';
$string['maximum'] = 'Maximum';
$string['messagereceived'] = 'Message received';
$string['messagesent'] = 'Message sent';
$string['mute'] = 'Mute';
$string['noisesuppresion'] = 'Noise suppresion';
$string['noisesuppresion_desc'] = 'Reduce noise that does not sound like speech. This may distort music or other sounds';
$string['overview'] = 'Overview';
$string['openinpopup'] = 'Open in popup';
$string['openinwindow'] = 'Open in window';
$string['notregistered'] = 'The service is not activated yet. That needs to be done in the Deft response block settings.';
$string['pluginname'] = 'Deft response';
$string['position'] = 'Position';
$string['preventresponse'] = 'Prevent response';
$string['privacy:metadata:core_comment'] = 'A record of comments added.';
$string['privacy:metadata:block_deft_response'] = 'Information about the user\'s answer(s) for a given task';
$string['privacy:metadata:block_deft_response:task'] = 'The ID of the task';
$string['privacy:metadata:block_deft_response:response'] = 'The text of the response that the user provided.';
$string['privacy:metadata:block_deft_response:userid'] = 'The ID of the user answering this task';
$string['privacy:metadata:block_deft_response:timemodified'] = 'The timestamp indicating when the choice was modified by the user';
$string['privacy:metadata:lti_client'] = 'LTI connection to deftly.us';
$string['privacy:metadata:lti_client:context'] = 'The Deft response block configures an external LTI connection to send messages that user information in a particular Moodle context may have been updated; however, no actual infomation is exported. The block loads a client that connects to the external site to recieve messages, but does not provide information other than establishing the connection.';
$string['raisehand'] = 'Raise hand';
$string['rejoin'] = 'Rejoin';
$string['response'] = 'Response';
$string['responses'] = 'Responses';
$string['returntosettings'] = 'Return to Deft response block settings';
$string['samplerate'] = 'Sample rate';
$string['samplerate_desc'] = 'Number of samples recorded per second of audio.';
$string['saveallchanges'] = 'Save all changes';
$string['search:choice'] = 'Deft response choice task';
$string['search:comments'] = 'Deft response comments task';
$string['search:text'] = 'Deft response text task';
$string['search:venue'] = 'Deft response venue task';
$string['showcomments'] = 'Show comments';
$string['showsummary'] = 'Show summary';
$string['showtitle'] = 'Show title';
$string['statusok'] = 'Successfully connected with service. Usage displayed below.';
$string['taskname'] = 'Task name';
$string['testconnection'] = 'Test connection';
$string['toolconfigured'] = 'The tool is configured now with deftly.us. The content of the Deft response block should update itself deftly when there are changes.';
$string['throttle_desc'] = 'When updating content is enabled, this sets a minumum time between updates to the clients in milliseconds';
$string['throttle'] = 'Throttle period';
$string['windowoption'] = 'Window option';
$string['unmute'] = 'Unmute';
$string['unsupportedbrowser'] = 'Unsupported browser';
$string['unsupportedbrowsermessage'] = 'This browser does not have real time support. Please try again with a supported browser.';
$string['venue'] = 'Venue';
$string['venueclosed'] = 'This venue is currently closed. A moderator will have to open it before you can participate.';
$string['venuesettings'] = 'Venue settings';
$string['visible'] = 'Visible';
$string['cleanuptask'] = 'Cleanup task for Deft response block';
