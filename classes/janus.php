<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Janus web service client
 *
 * @package    block_deft
 * @copyright  2023 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_deft;

use cache;
use context;
use moodle_exception;
use stdClass;

/**
 * Janus web service client
 *
 * @package    block_deft
 * @copyright  2023 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class janus {
    /**
     * @var Session id
     */
    protected int $session = 0;

    /**
     * @var Base url for the Janus server
     */
    protected string $baseurl = 'https://deftly.us/janus';

    /**
     * Constructor
     *
     * @param int|null $session Session handle if previously attached
     */
    public function __construct(?int $session = null) {
        if ($session) {
            $this->session = $session;
        } else {
            $this->session = $this->create_session();
        }
    }

    /**
     * Start session with server
     *
     * @return int Session id
     */
    public function create_session(): int {
        $content = [
            'janus' => 'create',
            'transaction' => $this->transaction_identifier(),
        ];

        $context = $this->create_context($content);
        $response = json_decode(file_get_contents($this->baseurl, false, $context));

        return $response->data->id;
    }

    /**
     * Attach plugin to session
     *
     * @param string $plugin Plugin identifier
     * @return int Plugin handle id
     */
    public function attach(string $plugin): int {

        $context = $this->create_context([
            'janus' => 'attach',
            'plugin' => $plugin,
            'transaction' => $this->transaction_identifier(),
        ]);
        $response = json_decode(file_get_contents("$this->baseurl/$this->session", false, $context));

        return $response->data->id;
    }

    /**
     * Send pluging message to server
     *
     * @param int $handle Plugin handle id
     * @param array|stdClass $message The content to be sent as object or associative array
     * @return stdClass Response from server
     */
    public function send(int $handle, $message): stdClass {
        $transaction = $this->transaction_identifier();

        $context = $this->create_context([
            'janus' => 'message',
            'body' => $message,
            'transaction' => $transaction,
        ]);

        $response = json_decode(file_get_contents("$this->baseurl/$this->session/$handle", false, $context));

        return $response;
    }

    /**
     * Wait for server message
     *
     * @return stdClass message
     */
    public function poll_session() {
        return json_decode(file_get_contents("$this->baseurl/$this->session"));
    }

    /**
     * Random string for transaction id
     *
     * @return string
     */
    public function transaction_identifier(): string {
        return sprintf('%x', random_int(0, 16 ** 12));
    }

    /**
     * Create context from data to post
     *
     * @param array $content
     * @return resource
     */
    public function create_context($content) {
        return stream_context_create([
            'http' => [
                'method' => 'POST',
                'content' => json_encode($content),
                'header' => 'Content-type: application/json',
            ],
        ]);
    }
}
