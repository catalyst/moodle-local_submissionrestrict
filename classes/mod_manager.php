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

namespace local_submissionrestict;

/**
 * Manager class.
 *
 * @package     local_submissionrestict
 * @copyright   2021 Catalyst IT
 * @author      Dmitrii Metelkin (dmitriim@catalyst-au.net)
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_manager {

    /**
     * A list of available mods.
     * @var ?mod_base[]
     */
    private static $mods = null;

    /**
     * Get an array listing all existing supported mods.
     *
     * @return mod_base[]
     */
    public static function get_mods(): array {
        if (!is_null(self::$mods)) {
            return self::$mods;
        }

        $dir = __DIR__.'/local/mod';

        if (!is_dir($dir)) {
            return [];
        }

        static::$mods = [];

        $files = new \DirectoryIterator($dir);
        foreach ($files as $item) {
            if ($item->isDot() || $item->isDir()) {
                continue;
            }

            $filename = $item->getFilename();
            $classname = preg_replace('/\.php$/', '', $filename);

            $namespaced = "\\local_submissionrestict\\local\\mod\\{$classname}";
            $object = new $namespaced();
            if ($object instanceof mod_base) {
                self::$mods[$classname] = new $namespaced();
            }
        }

        return self::$mods;
    }

    /**
     * Get an array listing all functional mods.
     *
     * @return mod_base[]
     */
    public static function get_functional_mods(): array {
        $functionalmods = [];

        foreach (self::get_mods() as $classname => $mod) {
            if ($mod->is_functional()) {
                $functionalmods[$classname] = $mod;
            }
        }

        return $functionalmods;
    }

}
