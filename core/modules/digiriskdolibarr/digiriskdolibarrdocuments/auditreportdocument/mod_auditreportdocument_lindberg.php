<?php
/* Copyright (C) 2024 EVARISK <technique@evarisk.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * or see https://www.gnu.org/
 */

/**
 * \file    core/modules/digiriskdolibarr/digiriskdolibarrdocuments/auditreportdocument/mod_auditreportdocument_lindberg.php
 * \ingroup digiriskdolibarr
 * \brief   File that contains the numbering module rules lindberg
 */

// Load Saturne libraries
require_once __DIR__ . '/../../../../../../saturne/core/modules/saturne/modules_saturne.php';

/**
 * Class of file that contains the numbering module rules lindberg
 */
class mod_auditreportdocument_lindberg extends CustomModeleNumRefSaturne
{
    /**
     * @var string Model name
     */
    public string $name = 'Lindberg';

    public function __construct()
    {
        global $conf;

        $refMod = $conf->global->DIGIRISKDOLIBARR_AUDITREPORTDOCUMENT_LINDBERG_ADDON;
        if (dol_strlen($refMod)) {
            $refModSplitted = preg_split('/\{/', $refMod);
            if (is_array($refModSplitted) && !empty($refModSplitted)) {
                $suffix       = preg_replace('/}/', '', $refModSplitted[1]);
                $this->prefix = $refModSplitted[0];
                $this->suffix = $suffix;
            }
        }
    }
}
