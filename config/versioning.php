<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Composer file
    |--------------------------------------------------------------------------
    |
    | The file that owns the application version. Relative paths are resolved
    | from the application base path.
    |
    */

    'composer_json' => 'composer.json',

    /*
    |--------------------------------------------------------------------------
    | Pre-release identifier
    |--------------------------------------------------------------------------
    |
    | Used by the prerelease, prepatch, preminor and premajor bumps. A version
    | looks like "1.2.3" without a pre-release and "1.2.3-beta.1" with one.
    |
    */

    'preid' => 'beta',

    /*
    |--------------------------------------------------------------------------
    | Commit message
    |--------------------------------------------------------------------------
    |
    | The ":version" placeholder is replaced with the new version.
    |
    */

    'commit_message' => 'chore(release): :version',

    /*
    |--------------------------------------------------------------------------
    | Tag prefix
    |--------------------------------------------------------------------------
    |
    | A version of 1.2.3 is tagged as "v1.2.3" by default.
    |
    */

    'tag_prefix' => 'v',

    /*
    |--------------------------------------------------------------------------
    | Git remote
    |--------------------------------------------------------------------------
    |
    | The remote used when the --push option is passed.
    |
    */

    'remote' => 'origin',

];
