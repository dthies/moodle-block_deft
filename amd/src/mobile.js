/*
 * Open and maintain a WebSocket to recieve messages from server.
 *
 * @package    block_deft
 * @module     block_deft/mobile
 * @copyright  2022 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (this.CONTENT_OTHERDATA) {
    var ws = new WebSocket('wss://deftly.us/ws'),
        token = this.CONTENT_OTHERDATA.token;

    ws.onopen = function() {
        ws.send(token);
    };

    ws.onclose = () => {
        var id = setInterval(() => {
            if (navigator.onLine) {
                clearInterval(id);
                this.refreshContent(false);
            }
        }, 5000);
    };

    ws.addEventListener('message', () => {
        setTimeout(function() {
            if (navigator.onLine) {
                this.refreshContent(false);
            }
        }.bind(this));
    });
}
