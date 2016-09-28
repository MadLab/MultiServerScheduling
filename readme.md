# Multi Server Scheduling

This package extends Laravel's native task scheduling to include the ability to block events from overlapping when in a multi webserver environment.

It works similarly to Laravel's `withoutOverlapping` feature, except the lockfile is written to Cache as opposed to the local filesystem, and each server generates a unique key to lock the command. 

In order to prevent a condition where a short-running command's lock doesn't last long enough, we are implementing a minimum 10 second break between the completion of the command and its next execution time, so if a command runs every minute but takes between 50 and 59 seconds to complete, the next command will be delayed one more minute. We also automatically expire any locks after 1 hour. 

You may also enable logging to Laravels logfile, so that you can ensure things are working correctly. 

## Installation


```
$ composer require madlab/multi-server-scheduling
```

The new scheduler uses Laravel's cache to track which server is currently executing an event. You must make sure you have configured a cache driver that is distributed and accessible by all servers (like memcache or redis).

Now we want to change the default schedule IoC to use this alternate one.  In app\Console\Kernel.php add the following function:

```php
/**
 * Define the application's command schedule.
 *
 * @return void
 */
protected function defineConsoleSchedule()
{
    $this->app->instance(
        'Illuminate\Console\Scheduling\Schedule', $schedule = new \madlab\MultiServerScheduler\Schedule(\madlab\MultiServerScheduler\Schedule::LOG_LEVEL_CONFLICTS)
    );

    $this->schedule($schedule);
}
```

## Usage

When composing your schedule, simply add "withoutOverlappingCache()" to the command, i.e.

```php
$schedule->command('inspire')
    ->daily()
    ->withoutOverlappingCache();
```

This will prevent multiple servers from executing the same event at the same time.

## Logging

When intitializing the Scheduler in app\Console\Kernal.php, you may pass in 3 different loggin levels:
- LOG_LEVEL_NONE: logging is disabled
- LOG_LEVEL_ABANDONED: a log will be written anytime a server tries to execute a task that has already been running for 10+ minutes
- LOG_LEVEL_VERBOSE: most detailed, will log anytime a lock is attempted, obtained, or released 