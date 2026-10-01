<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use MikeBronner\FontAwesomeToFluxImporter\Providers\ServiceProvider;

const PRO_TOKEN = "fa-test-token-5f1e2d";
const PRO_METADATA_URL = "https://npm.fontawesome.com/@fortawesome%2ffontawesome-pro";
const PRO_TARBALL_BASE = "https://npm.fontawesome.com/@fortawesome/fontawesome-pro/-/fontawesome-pro";
const FREE_METADATA_URL = "https://registry.npmjs.org/@fortawesome%2ffontawesome-free";
const FREE_TARBALL_BASE = "https://registry.npmjs.org/@fortawesome/fontawesome-free/-/fontawesome-free";

function proSvgs(): array
{
    return collect([
        "regular", "solid", "light", "thin",
        "duotone-regular", "duotone", "duotone-light", "duotone-thin",
        "sharp-regular", "sharp-solid", "sharp-light", "sharp-thin",
        "sharp-duotone-regular", "sharp-duotone-solid", "sharp-duotone-light", "sharp-duotone-thin",
    ])
        ->mapWithKeys(fn (string $variant) => ["{$variant}/heart.svg" => "M-{$variant}"])
        ->put("brands/github.svg", "M-brands")
        ->all();
}

function freeSvgs(): array
{
    return [
        "regular/heart.svg" => "M-regular",
        "solid/heart.svg" => "M-solid",
        "brands/github.svg" => "M-brands",
    ];
}

function fakeProRegistry(array $svgs, string $latest = "7.0.0", int $metadataStatus = 200, int $tarballStatus = 200): void
{
    $tarball = fakeTarball($svgs);

    Http::fake([
        PRO_METADATA_URL => Http::response(
            registryMetadata(PRO_TARBALL_BASE, [$latest => $tarball], $latest),
            $metadataStatus,
        ),
        PRO_TARBALL_BASE . "-{$latest}.tgz" => Http::response($tarball, $tarballStatus),
    ]);
}

function fakeFreeRegistry(array $tarballs, string $latest): void
{
    Http::fake(collect($tarballs)
        ->mapWithKeys(fn (string $bytes, string $version) => [
            FREE_TARBALL_BASE . "-{$version}.tgz" => Http::response($bytes),
        ])
        ->put(FREE_METADATA_URL, Http::response(registryMetadata(FREE_TARBALL_BASE, $tarballs, $latest)))
        ->all());
}

it("downloads Pro with the token and generates every family", function () {
    config(["font-awesome-to-flux.token" => PRO_TOKEN]);
    fakeProRegistry(proSvgs());

    $this->artisan("flux:import-fontawesome")
        ->expectsOutputToContain("Using @fortawesome/fontawesome-pro 7.0.0.")
        ->doesntExpectOutputToContain(PRO_TOKEN)
        ->assertSuccessful();

    $families = [
        "heart" => ["regular", "solid"],
        "brands/github" => ["brands", "brands"],
        "light/heart" => ["light", "solid"],
        "thin/heart" => ["thin", "solid"],
        "duotone/heart" => ["duotone-regular", "duotone"],
        "duotone/light/heart" => ["duotone-light", "duotone"],
        "duotone/thin/heart" => ["duotone-thin", "duotone"],
        "sharp/heart" => ["sharp-regular", "sharp-solid"],
        "sharp/light/heart" => ["sharp-light", "sharp-solid"],
        "sharp/thin/heart" => ["sharp-thin", "sharp-solid"],
        "sharp/duotone/heart" => ["sharp-duotone-regular", "sharp-duotone-solid"],
        "sharp/duotone/light/heart" => ["sharp-duotone-light", "sharp-duotone-solid"],
        "sharp/duotone/thin/heart" => ["sharp-duotone-thin", "sharp-duotone-solid"],
    ];

    foreach ($families as $icon => [$outline, $solid]) {
        expect(generatedIcon($icon))->toBeFile()
            ->and(File::get(generatedIcon($icon)))
            ->toContain("<path d=\"M-{$outline}\"/>")
            ->toContain("<path d=\"M-{$solid}\"/>");
    }

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), "https://npm.fontawesome.com/")
        && $request->header("Authorization") === ["Bearer " . PRO_TOKEN]);
    Http::assertNotSent(fn (Request $request) => $request->header("Authorization") !== ["Bearer " . PRO_TOKEN]);
});

it("downloads Free from the public registry without authentication when no token is set", function (?string $token) {
    config(["font-awesome-to-flux.token" => $token]);
    fakeFreeRegistry(["7.3.1" => fakeTarball(freeSvgs())], "7.3.1");

    $this->artisan("flux:import-fontawesome")
        ->expectsOutputToContain("FONTAWESOME_NPM_TOKEN is not set, so FontAwesome Free is used.")
        ->expectsOutputToContain("Using @fortawesome/fontawesome-free 7.3.1.")
        ->assertSuccessful();

    expect(File::get(generatedIcon("heart")))->toContain("M-regular", "M-solid")
        ->and(File::get(generatedIcon("brands/github")))->toContain("M-brands");

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request) => $request->url() === FREE_METADATA_URL);
    Http::assertNotSent(fn (Request $request) => $request->hasHeader("Authorization"));
})->with([
    "unset" => [null],
    "blank" => ["  "],
]);

it("never reads icons from node_modules", function () {
    $nodeModulesIcon = base_path("node_modules/@fortawesome/fontawesome-free/svgs/regular/ghost.svg");
    File::ensureDirectoryExists(dirname($nodeModulesIcon));
    File::put($nodeModulesIcon, svgIcon("M-ghost"));
    fakeFreeRegistry(["7.3.1" => fakeTarball(freeSvgs())], "7.3.1");

    try {
        $this->artisan("flux:import-fontawesome")->assertSuccessful();

        expect(generatedIcon("ghost"))->not->toBeFile()
            ->and(generatedIcon("heart"))->toBeFile();
    } finally {
        File::deleteDirectory(base_path("node_modules"));
    }
});

it("downloads the pinned version instead of the latest", function () {
    config(["font-awesome-to-flux.version" => "6.5.0"]);
    fakeFreeRegistry([
        "6.5.0" => fakeTarball(["regular/pinned.svg" => "M-pinned"]),
        "7.3.1" => fakeTarball(["regular/latest.svg" => "M-latest"]),
    ], "7.3.1");

    $this->artisan("flux:import-fontawesome")
        ->expectsOutputToContain("Using @fortawesome/fontawesome-free 6.5.0.")
        ->assertSuccessful();

    expect(generatedIcon("pinned"))->toBeFile()
        ->and(generatedIcon("latest"))->not->toBeFile();
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), "7.3.1"));
});

it("fails when the pinned version does not exist", function () {
    config(["font-awesome-to-flux.version" => "9.9.9"]);
    fakeFreeRegistry(["7.3.1" => fakeTarball(freeSvgs())], "7.3.1");

    $this->artisan("flux:import-fontawesome")
        ->expectsOutputToContain("Version '9.9.9' of @fortawesome/fontawesome-free was not found in the registry.")
        ->assertFailed();

    expect(generatedIcon("heart"))->not->toBeFile();
});

it("fails without revealing the token when the registry rejects it", function (int $metadataStatus, int $tarballStatus, int $reported) {
    config(["font-awesome-to-flux.token" => PRO_TOKEN]);
    fakeProRegistry(proSvgs(), metadataStatus: $metadataStatus, tarballStatus: $tarballStatus);

    $this->artisan("flux:import-fontawesome")
        ->expectsOutputToContain("FontAwesome's registry rejected FONTAWESOME_NPM_TOKEN (HTTP {$reported}).")
        ->doesntExpectOutputToContain(PRO_TOKEN)
        ->assertFailed();

    expect(generatedIcon("heart"))->not->toBeFile();
})->with([
    "metadata 401" => [401, 200, 401],
    "metadata 403" => [403, 200, 403],
    "tarball 401" => [200, 401, 401],
    "tarball 403" => [200, 403, 403],
]);

it("fails on any other registry error", function () {
    config(["font-awesome-to-flux.token" => PRO_TOKEN]);
    fakeProRegistry(proSvgs(), metadataStatus: 500);

    $this->artisan("flux:import-fontawesome")
        ->expectsOutputToContain("The package information request for @fortawesome/fontawesome-pro failed (HTTP 500).")
        ->assertFailed();
});

it("fails without revealing the token when the registry cannot be reached", function () {
    config(["font-awesome-to-flux.token" => PRO_TOKEN]);
    Http::fake(fn () => throw new ConnectionException("cURL error 6 for " . PRO_METADATA_URL));

    $this->artisan("flux:import-fontawesome")
        ->expectsOutputToContain("Could not connect to https://npm.fontawesome.com.")
        ->doesntExpectOutputToContain(PRO_TOKEN)
        ->assertFailed();
});

it("refuses a tarball hosted outside the registry and never sends the token there", function () {
    config(["font-awesome-to-flux.token" => PRO_TOKEN]);
    $tarball = fakeTarball(proSvgs());
    Http::fake([
        PRO_METADATA_URL => Http::response(registryMetadata("https://evil.example/fontawesome-pro", ["7.0.0" => $tarball], "7.0.0")),
        "https://evil.example/*" => Http::response($tarball),
    ]);

    $this->artisan("flux:import-fontawesome")
        ->expectsOutputToContain("is not on npm.fontawesome.com.")
        ->assertFailed();

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), "evil.example"));
});

it("refuses a tarball that is not served over https", function () {
    $tarball = fakeTarball(freeSvgs());
    Http::fake([
        FREE_METADATA_URL => Http::response(registryMetadata("http://registry.npmjs.org/fontawesome-free", ["7.3.1" => $tarball], "7.3.1")),
        "http://registry.npmjs.org/*" => Http::response($tarball),
    ]);

    $this->artisan("flux:import-fontawesome")
        ->expectsOutputToContain("is not on registry.npmjs.org.")
        ->assertFailed();

    Http::assertSentCount(1);
});

it("refuses a tarball that fails its integrity check", function (string $case) {
    $tarball = fakeTarball(freeSvgs());
    $metadata = registryMetadata(FREE_TARBALL_BASE, ["7.3.1" => $tarball], "7.3.1");
    $metadata["versions"]["7.3.1"]["dist"]["integrity"] = match ($case) {
        "mismatched hash" => integrityOf("something else"),
        "missing" => "",
        "weak algorithm" => "md5-" . base64_encode(md5($tarball, true)),
    };
    Http::fake([
        FREE_METADATA_URL => Http::response($metadata),
        FREE_TARBALL_BASE . "-7.3.1.tgz" => Http::response($tarball),
    ]);

    $this->artisan("flux:import-fontawesome")
        ->expectsOutputToContain("The download of @fortawesome/fontawesome-free 7.3.1 failed its integrity check.")
        ->assertFailed();

    expect(generatedIcon("heart"))->not->toBeFile();
})->with([
    "mismatched hash",
    "missing",
    "weak algorithm",
]);

it("fails when the package has no svgs directory", function () {
    $dir = storage_path("framework/testing/fontawesome/no-svgs");
    File::ensureDirectoryExists($dir);
    $tar = new PharData("{$dir}/fixture.tar");
    $tar->addFromString("package/package.json", "{}");
    $tar->compress(Phar::GZ);
    $tarball = File::get("{$dir}/fixture.tar.gz");
    fakeFreeRegistry(["7.3.1" => $tarball], "7.3.1");

    $this->artisan("flux:import-fontawesome")
        ->expectsOutputToContain("@fortawesome/fontawesome-free 7.3.1 contains no svgs directory.")
        ->assertFailed();
});

it("writes the same stub output as before", function () {
    fakeFreeRegistry(["7.3.1" => fakeTarball(freeSvgs())], "7.3.1");

    $this->artisan("flux:import-fontawesome")->assertSuccessful();

    $expected = str_replace(
        ["{SVG_ATTRIBUTES}", "{OUTLINE}", "{SOLID}"],
        ["xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 512 512\"", "<path d=\"M-regular\"/>", "<path d=\"M-solid\"/>"],
        File::get(__DIR__ . "/../../stubs/flux/icon.blade.php"),
    );

    expect(File::get(generatedIcon("heart")))->toBe($expected);
});

it("replaces previously generated icons", function () {
    File::ensureDirectoryExists(dirname(generatedIcon("stale")));
    File::put(generatedIcon("stale"), "old");
    fakeFreeRegistry(["7.3.1" => fakeTarball(freeSvgs())], "7.3.1");

    $this->artisan("flux:import-fontawesome")->assertSuccessful();

    expect(generatedIcon("stale"))->not->toBeFile()
        ->and(generatedIcon("heart"))->toBeFile();
});

it("keeps previously generated icons when the download fails", function () {
    File::ensureDirectoryExists(dirname(generatedIcon("existing")));
    File::put(generatedIcon("existing"), "kept");
    config(["font-awesome-to-flux.token" => PRO_TOKEN]);
    fakeProRegistry(proSvgs(), metadataStatus: 401);

    $this->artisan("flux:import-fontawesome")->assertFailed();

    expect(generatedIcon("existing"))->toBeFile();
});

it("removes the downloaded package after the run", function () {
    fakeFreeRegistry(["7.3.1" => fakeTarball(freeSvgs())], "7.3.1");

    $this->artisan("flux:import-fontawesome")->assertSuccessful();

    expect(glob(storage_path("framework/fontawesome-*")))->toBeEmpty();
});

it("publishes the config file", function () {
    expect(ServiceProvider::pathsToPublish(ServiceProvider::class, "font-awesome-to-flux-config"))
        ->toHaveCount(1)
        ->toContain(config_path("font-awesome-to-flux.php"))
        ->and(config("font-awesome-to-flux"))->toHaveKeys(["token", "version"]);
});
