#!/usr/bin/env bash
# @package   akeebabackup
# @copyright Copyright (c)2006-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
# @license   GNU General Public License version 3, or later
#

find . -type f -name '*.php' | xargs grep -H -c "defined('_JEXEC')" | grep 0$ | cut -d':' -f1