<?php

return [
    /*
    |--------------------------------------------------------------------------
    | GitHub fine-grained PAT for triggering deploy workflows
    |--------------------------------------------------------------------------
    | Scope required: Actions: Read & Write on this repository only.
    | Never logged or exposed to the browser.
    */
    'github_token' => env('GITHUB_DEPLOY_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | GitHub repository (owner/repo)
    |--------------------------------------------------------------------------
    */
    'github_repo' => env('GITHUB_DEPLOY_REPO', 'raiika-nio-my-id/singlepage'),

    /*
    |--------------------------------------------------------------------------
    | Workflow file name
    |--------------------------------------------------------------------------
    */
    'workflow_file' => 'deploy.yml',
];
