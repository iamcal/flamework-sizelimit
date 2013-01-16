# lib_sizelimit

This library registers a shutdown function which checks the process size of the current Apache child.
If the child exeeds the configured memory limit, it will be shutdown cleanly after the request (and restarted).
This is a safeguard against processes growing bloated because of bad code and bringing down your server.

This probably only works for Apache on Linux, with prefork MPM, but should silently do nothing on other platforms.

This library is designed to be used with <a href="https://github.com/exflickr/flamework">Flamework</a>, but works standalone too.
You'll need to remove the calls to `log_notice()` if you're not using Flamework.


## Usage

    # Maximum total (non-shared) process size, in bytes
    $GLOBALS['cfg']['sizelimit_max_mem'] = 30 * 1024 * 1024;

    # Page size, in bytes - use `getconf PAGESIZE` to check it's 4096
    $GLOBALS['cfg']['sizelimit_page_size'] = 4096;

    include('lib_sizelimit.php');

That's it - it will just do its thing, logging a notice about memory usage at the end of each run.
It will log a second notice if the child is going to be terminated.
