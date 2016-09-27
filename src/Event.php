<?php

namespace madlab\CacheLockEvent;

use Illuminate\Console\Scheduling\Event as NativeEvent;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Cache;

class Event extends NativeEvent
{
    /**
     * Hash we will use to identify our server and lock the process.
     * @var string
     */
    protected $serverId;

    /**
     * Hash of the command mutex we will use to uniquely identify the command.
     * @var string
     */
    protected $key;

    /**
     * Create a new event instance.
     *
     * @param  string  $command
     * @return void
     */
    public function __construct($command)
    {
        parent::__construct($command);
        $this->serverId = str_random(32);
    }

    /**
     * Run the given event, then clear our lock.
     *
     * @param  \Illuminate\Contracts\Container\Container  $container
     * @return void
     */
    public function run(Container $container)
    {
        parent::run($container);
        $this->clearCache();
    }

    /**
     * Prevents this command from executing across multiple servers attempting
     * at the same time.
     * @return $this
     */
    public function withoutOverlappingCache()
    {
        return $this->skip(function () {
            return $this->skipMultiserver();
        });
    }

    /**
     * Attempt to lock this command.
     * @return bool true if we want to skip
     */
    public function skipCache()
    {
        $this->key = md5($this->expression.$this->command);


        // See if we can find this task in the cache
        if($eventInfo = Cache::get($this->key)) {
            // Task found. Is it abandoned?
            $startDate = $eventInfo->get('startDate');
            $now = \Carbon\Caron::now();
            if(empty($eventInfo->get('endDate')) && $now->diffInHours($startDate, true) >=1){
                //Task has been abandonded. Delete it.
                Cache::forget($this->key);
            }
            else{
                //Someone else has the lock.
                return true;
            }
        }

        // Attempt to acquire the lock
        $eventInfo = collect([]);
        $eventInfo->put('command',$this->command);
        $eventInfo->put('expression',$this->expression);
        $eventInfo->put('lock',$this->serverId);
        $eventInfo->put('startDate',\Carbon\Carbon::now());

        // If the mutex already exists in the cache, the above 'add' will return false
        if(Cache::add($this->key, $eventInfo)){
            //we got the lock
            return false;
        }

        // Someone else has the lock
        return true;
    }

    /**
     * Delete our locks.
     * @return void 
     */
    public function clearCache()
    {
        // Set the lock to expire in 10 seconds
        if ($this->serverId) {
            $eventInfo = Cache::get($this->key);
            $eventInfo->put('endDate',\Carbon\Carbon::now());
            Cache::put($this->key, $eventInfo, \Carbon\Carbon::now()->addSeconds(10));
        }
    }
}
