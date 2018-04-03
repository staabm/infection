<?php
/**
 * Copyright © 2017-2018 Maks Rafalko
 *
 * License: https://opensource.org/licenses/BSD-3-Clause New BSD License
 */

declare(strict_types=1);

namespace Infection\Console\OutputFormatter;

use Infection\Process\MutantProcessInterface;

/**
 * Abstract empty class to simplify particular implementations
 */
abstract class AbstractOutputFormatter implements OutputFormatter
{
    protected $callsCount = 0;

    public function start(int $mutationCount)
    {
        $this->callsCount = 0;
    }

    public function advance(MutantProcessInterface $mutantProcess, int $mutationCount)
    {
        ++$this->callsCount;
    }

    public function finish()
    {
    }
}
