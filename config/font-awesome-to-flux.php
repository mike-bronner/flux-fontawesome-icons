<?php

declare(strict_types=1);

return [
    /*
    | The Font Awesome npm package token from your Font Awesome account. When it is
    | set, the Pro package is downloaded. When it is empty, Free is downloaded.
    | Keep it in .env locally and in a secret in CI. Never commit it.
    */
    "token" => env("FONTAWESOME_NPM_TOKEN"),

    /*
    | The package version to download, for example "7.1.0". Pin it so every build
    | generates the same icons. When it is empty, the latest version is used.
    */
    "version" => env("FONTAWESOME_VERSION"),
];
