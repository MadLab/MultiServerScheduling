<?php

namespace madlab\MultiServerScheduling;

use Illuminate\Console\Scheduling\Schedule as NativeSchedule;
use Illuminate\Contracts\Cache\Repository as Cache;

class Schedule extends NativeSchedule
{
    const LOG_LEVEL_NONE = 0;
    const LOG_LEVEL_ABANDONED = 1;
    const LOG_LEVEL_VERBOSE = 2;

    private $logLevel;

    /**
     * Schedule constructor.
     * @param int $logLevel
     */
    public function __construct(Cache $cache, $logLevel = self::LOG_LEVEL_NONE)
    {
        parent::__construct($cache);
        $this->logLevel = $logLevel;
    }

    /**
     * Add a new command event to the schedule.
     *
     * @param  string  $command
     * @param  array  $parameters
     * @return \Illuminate\Console\Scheduling\Event
     */
    public function exec($command, array $parameters = [])
    {
        if (count($parameters)) {
            $command .= ' '.$this->compileParameters($parameters);
        }

        $this->events[] = $event = new Event($this->cache, $command, $this->logLevel);

        return $event;
    }
}
