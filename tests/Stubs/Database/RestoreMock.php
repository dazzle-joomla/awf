<?php
/**
 * @package		awf
 * @copyright	2014-2017 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license		GNU GPL version 3 or later
 */

namespace Awf\Tests\Stubs\Database;

use Awf\Database\Restore;

class RestoreMock extends Restore
{
    protected function processQueryLine($query)
    {
        return $query;
    }

}
