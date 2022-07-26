/*
 * Register platform with service tool
 *
 * @package    block_deft
 * @module     block_deft/configure
 * @copyright  2022 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export default {
    /**
     * Register platform
     *
     * @param {string} registrationurl The local url with parameters to register
     * @param {string} url The return url to settings
     */
    init: (registrationurl, url) => {
        document.querySelector('.registrationcontainer').innerHTML = '<iframe src="' +
            registrationurl + '" style="height: 400px; width: 400px;"></iframe>';
        window.addEventListener('message', () => {
            window.location.href = url;
        }, false);
    }
};
