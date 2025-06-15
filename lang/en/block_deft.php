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
$string['activatemessage'] = 'This will contact the server and deftly.us and obtain a registration URL to configure an LTI connection. It will then add an External tool configuration to your site. This will be used to authenticate messages sent to clients to update the block content. Your server needs to be publicly accessible to the internet for this to work.';
$string['addchoice'] = 'Add choice';
$string['addcomments'] = 'Add comments';
$string['addtext'] = 'Add text';
$string['addvenue'] = 'Add venue';
$string['appid'] = 'App id';
$string['appid_desc'] = 'This Jitsi server should be secured so only participants authorized by the activity can participate. The server administrator should set an App ID and secret to use here. See <a href="https://jitsi.github.io/handbook/docs/devops-guide/devops-guide-quickstart/">Jitsi documentation</a> to self host.';
$string['authorizationreceived'] = 'Authorization received';
$string['autogaincontrol'] = 'Auto gain control';
$string['autogaincontrol_desc'] = 'Automatically adjusts volume to standard level. This may help understand speech, but may amplify some noise and reduce dynamic changes in music.';
$string['bridgedconnection'] = 'Bridged connection';
$string['cachedef_comments'] = 'Deft choice comments';
$string['cachedef_results'] = 'Deft choice results';
$string['cachedef_tasks'] = 'Deft tasks';
$string['cachedef_tokens'] = 'Deft service auth tokens';
$string['changestatus'] = 'Change status';
$string['charttype'] = 'Display results in pie graph';
$string['cleanuptask'] = 'Cleanup task for Deft response block';
$string['close'] = 'Close';
$string['closevenue'] = 'Close venue';
$string['configquestion'] = 'Question';
$string['configtitle'] = 'Deft response block title';
$string['configuremanually'] = 'The automatic registration process has failed for some reason. The LTI tool must be configured manually. Contact the developers to report this and obtain the URLs for manual configuration.';
$string['confirmdelete'] = 'Confirm delete \'{$a}\'?';
$string['connections'] = '# of connections';
$string['connectiontype'] = 'Connection type';
$string['content'] = 'Content';
$string['count'] = 'Count';
$string['cumulative'] = 'Cumulative';
$string['current'] = 'Current';
$string['deft:addinstance'] = 'Add instance';
$string['deft:choose'] = 'Make choice';
$string['deft:edit'] = 'Edit tasks';
$string['deft:joinvenue'] = 'Join venue';
$string['deft:manage'] = 'Manage tasks';
$string['deft:moderate'] = 'Moderate venue';
$string['deft:myaddinstance'] = 'Add instance to dashboard';
$string['deft:sharevideo'] = 'Share video';
$string['deftbridge'] = 'Deftly hosted media server';
$string['deftsettings'] = 'Deftly hosted settings';
$string['deftsettings_desc'] = 'If hosted Deftly server is selected for either updating content or audio / video bridge above, then access should be enabled below.';
$string['echocancellation'] = 'Echo cancellation';
$string['echocancellation_desc'] = 'Attempt to remove echo from background.';
$string['editchoice'] = 'Edit choice';
$string['editcomments'] = 'Edit comments';
$string['edittext'] = 'Edit text';
$string['editvenue'] = 'Edit venue';
$string['enablebridge'] = 'Enable bridge';
$string['enablebridge_desc'] = 'Selecting a server type makes the option to configure audio bridge appear when editing a venue. The bridge allows more participants in the venue and better audio quality. You will need access to {$a} or install a Jitsi server and complete the appropriate settings section below.';
$string['enableservicemessage'] = 'Updating through the deftly.us is disabled. Enable the setting above and then activate the service to enable live updates to the block\'s content.';
$string['enableupdating'] = 'Enable updating';
$string['enableupdating_desc'] = 'Select server type to use. You will need to complete the section for the selected server type. If none is selected, content will only be updated when page reloads.';
$string['enablevideo'] = 'Enable video';
$string['enablevideo_desc'] = 'This makes the button to share video feed appear for users with <em>Share video</em> capability. You will need a plan from {$a} or other server configuration.';
$string['eventaudiobridgelaunched'] = 'Audio bridge launched';
$string['eventchoicesubmitted'] = 'Choice submitted';
$string['eventhandlowersent'] = 'Hand lower sent';
$string['eventhandraisesent'] = 'Hand raise sent';
$string['eventmuteswitched'] = 'Mute switched';
$string['eventtaskcreated'] = 'Task created';
$string['eventtaskdeleted'] = 'Task deleted';
$string['eventtaskupdated'] = 'Task updated';
$string['eventvenueended'] = 'Venue ended';
$string['eventvenuestarted'] = 'Venue started';
$string['eventvideoended'] = 'Video ended';
$string['eventvideostarted'] = 'Video started';
$string['expandcomments'] = 'Expand comments';
$string['guests'] = 'Guests';
$string['handloweredsent'] = 'Hand lowered';
$string['handraisesent'] = 'Hand raised';
$string['jitsibridge'] = 'Self hosted Jitsi server';
$string['jitsisettings'] = 'Jitsi settings';
$string['jitsisettings_desc'] = 'If self hosted Jitsi media server is selected for either updating content or audio / video bridge, then this section needs to be completed to use that server.';
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
$string['microphoneon'] = 'The microphone is on';
$string['mute'] = 'Mute';
$string['noisesuppression'] = 'Noise suppression';
$string['noisesuppression_desc'] = 'Reduce noise that does not sound like speech.  This may distort music or other sounds';
$string['notregistered'] = 'The service is not activated yet. That needs to be done in the Deft response block settings.';
$string['openinpopup'] = 'Open in popup';
$string['openinwindow'] = 'Open in window';
$string['overview'] = 'Overview';
$string['peerconnection'] = 'Peer connection';
$string['pluginname'] = 'Deft response';
$string['position'] = 'Position';
$string['preventresponse'] = 'Prevent response';
$string['privacy:connections'] = 'Connections';
$string['privacy:metadata:block_deft'] = 'Tasks that were modified by user';
$string['privacy:metadata:block_deft:id'] = 'Task id';
$string['privacy:metadata:block_deft:timemodified'] = 'Time modified';
$string['privacy:metadata:block_deft:usermodified'] = 'User id';
$string['privacy:metadata:block_deft_peer'] = 'Temporary data for state of user\'s connection in venue';
$string['privacy:metadata:block_deft_peer:mute'] = 'Whether mute';
$string['privacy:metadata:block_deft_peer:status'] = 'Whether finished';
$string['privacy:metadata:block_deft_peer:task'] = 'Venue task id';
$string['privacy:metadata:block_deft_peer:timecreated'] = 'Time created';
$string['privacy:metadata:block_deft_peer:timemodified'] = 'Time modified';
$string['privacy:metadata:block_deft_peer:type'] = 'Type of connection';
$string['privacy:metadata:block_deft_peer:userid'] = 'User id ';
$string['privacy:metadata:block_deft_peer:uuid'] = 'Mobile device id';
$string['privacy:metadata:block_deft_response'] = 'Information about the user\'s answer(s) for a given task';
$string['privacy:metadata:block_deft_response:response'] = 'The text of the response that the user provided.';
$string['privacy:metadata:block_deft_response:task'] = 'The ID of the task';
$string['privacy:metadata:block_deft_response:timemodified'] = 'The timestamp indicating when the choice was modified by the user';
$string['privacy:metadata:block_deft_response:userid'] = 'The ID of the user answering this task';
$string['privacy:metadata:block_deft_room'] = 'Server room assigned to venue';
$string['privacy:metadata:block_deft_room:itemid'] = 'Task id';
$string['privacy:metadata:block_deft_room:timemodified'] = 'Time modified';
$string['privacy:metadata:block_deft_room:usermodified'] = 'User id';
$string['privacy:metadata:core_comment'] = 'A record of comments added.';
$string['privacy:metadata:lti_client'] = 'LTI connection to deftly.us';
$string['privacy:metadata:lti_client:context'] = 'The Deft response block configures an external LTI connection to send messages that user information in a particular Moodle context may have been updated; however, no actual information is exported. The block loads a client that connects to the external site to receive messages, but does not provide information other than establishing the connection.';
$string['privacy:responses'] = 'Responses';
$string['privacy:rooms'] = 'Rooms';
$string['privacy:task'] = 'Task {$a}';
$string['privacy:tasks'] = 'Tasks';
$string['raisehand'] = 'Raise hand';
$string['reconnecting'] = 'Reconnecting';
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
$string['secret'] = 'Secret';
$string['secret_desc'] = 'This Jitsi server administrator should provide secret to access the server.';
$string['server'] = 'Server';
$string['server_desc'] = 'The host name for Jitsi server to use for meeting';
$string['serverinaccessible'] = 'Your server appears inaccessible to deftly.us. For the server to work your server needs to available from the internet. Servers only on a private network or localhost can not be registered.';
$string['serverlost'] = 'Server connection lost';
$string['sharevideo'] = 'Share video';
$string['showcomments'] = 'Show comments';
$string['showsummary'] = 'Show summary';
$string['showtitle'] = 'Show title';
$string['statusok'] = 'Successfully connected with service. Usage displayed below.';
$string['stopvideo'] = 'Stop video';
$string['taskname'] = 'Task name';
$string['testconnection'] = 'Test connection';
$string['throttle'] = 'Throttle period';
$string['throttle_desc'] = 'When updating content is enabled, this sets a minimum time between updates to the clients in milliseconds';
$string['toolconfigured'] = 'The tool is configured now with deftly.us. The content of the Deft response block should update itself deftly when there are changes.';
$string['unmute'] = 'Unmute';
$string['unsupportedbrowser'] = 'Unsupported browser';
$string['unsupportedbrowsermessage'] = 'This browser does not have real time support. Please try again with a supported browser.';
$string['updatesettings'] = 'Update settings';
$string['updatesettings_desc'] = 'Control the way content is updated in the browser.';
$string['venue'] = 'Venue';
$string['venueclosed'] = 'This venue is currently closed. A moderator will have to open it before you can participate.';
$string['venuesettings'] = 'Venue settings';
$string['venuesettings_desc'] = 'Parameters used for audio and video for the Venue task.';
$string['visible'] = 'Visible';
$string['windowoption'] = 'Window option';
