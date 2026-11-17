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
    | ":version" and ":previous" are replaced with the new and the current
    | version. The first line becomes the commit subject, the paragraphs after a
    | blank line become the commit body.
    |
    */

    'commit_message' => "chore(release): :version\n\nBump the version in composer.json to :version.",

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

    /*
    |--------------------------------------------------------------------------
    | Interactive git
    |--------------------------------------------------------------------------
    |
    | When disabled (the default) git cannot ask for a password, so a push to a
    | repository that needs one fails immediately instead of hanging. Enable
    | this when you release from an interactive terminal and rely on the prompt,
    | or configure a credential helper / SSH key instead.
    |
    */

    'interactive' => false,

];
