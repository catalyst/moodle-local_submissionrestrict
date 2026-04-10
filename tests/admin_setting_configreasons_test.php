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

namespace local_submissionrestrict;

use local_submissionrestrict\local\admin\admin_setting_configreasons;

/**
 * Tests for admin_setting_configreasons.
 *
 * @package    local_submissionrestrict
 * @copyright  2026 Catalyst IT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \local_submissionrestrict\local\admin\admin_setting_configreasons
 */
final class admin_setting_configreasons_test extends \advanced_testcase {
    /** @var admin_setting_configreasons */
    private admin_setting_configreasons $setting;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setting = new admin_setting_configreasons(
            'local_submissionrestrict/reasons',
            'Reasons',
            '',
            '',
        );
    }

    /**
     * Data provider for test_validate_returns_true_for_valid_input().
     *
     * @return array
     */
    public static function validate_valid_provider(): array {
        return [
            'empty string'                => [''],
            'whitespace only'             => ['   '],
            'label only'                  => ['Non-assessed activities'],
            'label with description'      => ['Non-assessed activities::Some description'],
            'multiple valid lines'        => ["Non-assessed activities::Some description\nextension::Extension granted"],
            'blank lines ignored'         => ["Non-assessed activities::Some description\n\n\nextension::Extension"],
            'windows line endings'        => ["Non-assessed activities::Some description\r\nextension::Extension"],
        ];
    }

    /**
     * Test validate() returns true for valid input.
     *
     * @dataProvider validate_valid_provider
     * @param string $input
     */
    public function test_validate_returns_true_for_valid_input(string $input): void {
        $this->assertTrue($this->setting->validate($input));
    }

    /**
     * Data provider for test_validate_returns_error_for_invalid_input().
     *
     * @return array
     */
    public static function validate_invalid_provider(): array {
        return [
            'delimiter with no label or description' => ['::'],
            'delimiter with empty description'       => ['Non-assessed activities::'],
            'delimiter with whitespace description'  => ['Non-assessed activities::   '],
            'empty label with description'           => ['::description'],
            'mixed valid and invalid lines'          => ["Non-assessed activities::Some description\nbad::"],
        ];
    }

    /**
     * Test validate() returns error for invalid input.
     *
     * @dataProvider validate_invalid_provider
     * @param string $input
     */
    public function test_validate_returns_error_for_invalid_input(string $input): void {
        $result = $this->setting->validate($input);
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Data provider for test_parse_reason_config().
     * @return array
     */
    public static function parse_reason_config_provider(): array {
        return [
            'null returns empty' => [
                null, [],
            ],
            'empty string returns empty' => [
                '', [],
            ],
            'whitespace only returns empty' => [
                '   ', [],
            ],
            'label only, empty description' => [
                'Non-assessed activities', ['Non-assessed activities' => ''],
            ],
            'label with description' => [
                'Non-assessed activities::Some description',
                ['Non-assessed activities' => 'Some description'],
            ],
            'multiple lines' => [
                "Non-assessed activities::Some description\nextension::Extension granted",
                ['Non-assessed activities' => 'Some description', 'extension' => 'Extension granted'],
            ],
            'blank lines are skipped' => [
                "Non-assessed activities::Some description\n\n\nextension::Extension",
                ['Non-assessed activities' => 'Some description', 'extension' => 'Extension'],
            ],
            'windows line endings normalised' => [
                "Non-assessed activities::Some description\r\nextension::Extension",
                ['Non-assessed activities' => 'Some description', 'extension' => 'Extension'],
            ],
            'labels and descriptions are trimmed' => [
                '  Non-assessed activities  ::  Some description  ',
                ['Non-assessed activities' => 'Some description'],
            ],
            'line with empty label is skipped' => [
                '::description', [],
            ],
            'delimiter but empty description' => [
                'Non-assessed activities::', ['Non-assessed activities' => ''],
            ],
            'last writer wins on duplicate label' => [
                "Non-assessed activities::First\nNon-assessed activities::Second",
                ['Non-assessed activities' => 'Second'],
            ],
        ];
    }

    /**
     * Test parse_reason_config().
     *
     * @dataProvider parse_reason_config_provider
     * @param null $input
     * @param array $expected
     */
    public function test_parse_reason_config(string|null $input, array $expected): void {
        $this->assertSame($expected, admin_setting_configreasons::parse_reason_config($input));
    }
}
