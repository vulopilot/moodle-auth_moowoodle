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
 * Setup wizard step registry and progress rendering.
 *
 * @package    auth_moowoodle
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2023 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_moowoodle\local;

/**
 * Setup wizard step registry and progress rendering.
 */
class setup_wizard {

    /**
     * Ordered list of wizard steps.
     *
     * @return array
     */
    public static function get_steps(): array {
        return [
            'requirements' => get_string('step_requirements', 'auth_moowoodle'),
            'webservice' => get_string('step_webservice', 'auth_moowoodle'),
            'connection' => get_string('step_connection', 'auth_moowoodle'),
            'summary' => get_string('step_summary', 'auth_moowoodle'),
        ];
    }

    /**
     * The step to show when no step is requested and no progress is stored.
     *
     * @return string
     */
    public static function get_first_step(): string {
        $steps = array_keys(self::get_steps());
        return reset($steps);
    }

    /**
     * The step after the given one, or '' if it was the last step.
     *
     * @param string $currentstep
     * @return string
     */
    public static function get_next_step(string $currentstep): string {
        $steps = array_keys(self::get_steps());
        $index = array_search($currentstep, $steps, true);

        if ($index === false || !isset($steps[$index + 1])) {
            return '';
        }

        return $steps[$index + 1];
    }

    /**
     * The step before the given one, or '' if it was the first step.
     *
     * @param string $currentstep
     * @return string
     */
    public static function get_prev_step(string $currentstep): string {
        $steps = array_keys(self::get_steps());
        $index = array_search($currentstep, $steps, true);

        if ($index === false || $index === 0) {
            return '';
        }

        return $steps[$index - 1];
    }

    /**
     * Whether the given step key is a valid step.
     *
     * @param string $step
     * @return bool
     */
    public static function is_valid_step(string $step): bool {
        return array_key_exists($step, self::get_steps());
    }

    /**
     * Render the step progress indicator using Moodle's standard tab UI.
     *
     * @param string $currentstep
     * @return string
     */
    public static function render_progress(string $currentstep): string {
        global $OUTPUT;

        $steps = self::get_steps();
        $stepkeys = array_keys($steps);

        $furthest = get_config('auth_moowoodle', 'setup_progress');
        $furthestindex = $furthest ? array_search($furthest, $stepkeys, true) : -1;

        $tabs = [];
        $inactive = [];

        foreach ($stepkeys as $index => $key) {
            $url = new \moodle_url('/auth/moowoodle/setup_wizard.php', ['step' => $key]);
            $tabs[] = new \tabobject($key, $url, $steps[$key]);

            // A step can only be jumped to once the wizard has reached it at least once.
            if ($index > $furthestindex + 1) {
                $inactive[] = $key;
            }
        }

        return $OUTPUT->tabtree($tabs, $currentstep, $inactive);
    }

    /**
     * Record that the given step has been completed, advancing stored progress.
     *
     * @param string $step
     */
    public static function mark_step_complete(string $step): void {
        $steps = array_keys(self::get_steps());
        $stored = get_config('auth_moowoodle', 'setup_progress');
        $storedindex = $stored ? array_search($stored, $steps, true) : -1;
        $stepindex = array_search($step, $steps, true);

        if ($stepindex !== false && $stepindex > $storedindex) {
            set_config('setup_progress', $step, 'auth_moowoodle');
        }
    }
}
