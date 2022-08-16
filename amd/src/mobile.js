/*
 * Open and maintain a WebSocket to recieve messages from server.
 *
 * @package    block_deft
 * @module     block_deft/socket
 * @copyright  2022 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (this.CONTENT_OTHERDATA) {
    var ws = new WebSocket('wss://deftly.us/ws'),
        token = this.CONTENT_OTHERDATA.token;

    ws.onopen = function() {
        ws.send(token);
    };

    ws.addEventListener('message', function() {
        setTimeout(function() {
            this.refreshContent(false);
        }.bind(this));
    }.bind(this));
}
