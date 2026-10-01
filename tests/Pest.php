<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use MikeBronner\FontAwesomeToFluxImporter\Tests\TestCase;

uses(TestCase::class)->in("Feature");

function svgIcon(string $path): string
{
    return "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 512 512\"><path d=\"{$path}\"/></svg>";
}

function fakeTarball(array $svgs): string
{
    return rawTarball(collect($svgs)
        ->mapWithKeys(fn (string $path, string $file) => ["svgs/{$file}" => svgIcon($path)])
        ->all());
}

function rawTarball(array $files): string
{
    $dir = storage_path("framework/testing/fontawesome/" . Str::random(8));
    File::ensureDirectoryExists($dir);
    $tar = new PharData("{$dir}/fixture.tar");

    foreach ($files as $file => $contents) {
        $tar->addFromString("package/{$file}", $contents);
    }

    $tar->compress(Phar::GZ);

    return File::get("{$dir}/fixture.tar.gz");
}

function integrityOf(string $bytes): string
{
    return "sha512-" . base64_encode(hash("sha512", $bytes, true));
}

function registryMetadata(string $tarballBase, array $tarballs, string $latest): array
{
    return [
        "dist-tags" => ["latest" => $latest],
        "versions" => collect($tarballs)
            ->mapWithKeys(fn (string $bytes, string $version) => [
                $version => [
                    "dist" => [
                        "tarball" => "{$tarballBase}-{$version}.tgz",
                        "integrity" => integrityOf($bytes),
                    ],
                ],
            ])
            ->all(),
    ];
}

function generatedIcon(string $path): string
{
    return resource_path("views/flux/icon/fontawesome/{$path}.blade.php");
}
