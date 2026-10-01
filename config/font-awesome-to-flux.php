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

    /*
    | The weight each family uses for Flux's outline variant: "regular", "light", or
    | "thin". The solid variant is always the family's own solid style.
    | When a weight is empty, the command asks for it in a terminal. Without a
    | terminal, as in CI or with --no-interaction, it uses "regular".
    | Free has only the classic family with the regular weight. Without the token,
    | the other families are skipped, and "light" or "thin" for classic fails.
    */
    "weights" => [
        "classic" => env("FONTAWESOME_CLASSIC_WEIGHT"),
        "sharp" => env("FONTAWESOME_SHARP_WEIGHT"),
        "duotone" => env("FONTAWESOME_DUOTONE_WEIGHT"),
        "sharp_duotone" => env("FONTAWESOME_SHARP_DUOTONE_WEIGHT"),
    ],
];
