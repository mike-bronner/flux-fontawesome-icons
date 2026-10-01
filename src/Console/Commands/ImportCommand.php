<?php

declare(strict_types=1);

namespace MikeBronner\FontAwesomeToFluxImporter\Console\Commands;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PharData;
use RuntimeException;

use function Laravel\Prompts\progress;
use function Laravel\Prompts\spin;

class ImportCommand extends Command
{
    // phpcs:disable SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingAnyTypeHint
    protected $signature = "flux:import-fontawesome";
    protected $description = "Download FontAwesome and convert its SVG icons to Flux icons";
    // phpcs:enable

    protected const PRO_REGISTRY = "https://npm.fontawesome.com";
    protected const FREE_REGISTRY = "https://registry.npmjs.org";

    // Each family maps to its outline and solid variant directories in the package.
    protected const FAMILIES = [
        "" => ["regular", "solid"],
        "brands" => ["brands", "brands"],
        "light" => ["light", "solid"],
        "thin" => ["thin", "solid"],
        "duotone" => ["duotone-regular", "duotone"],
        "duotone/light" => ["duotone-light", "duotone"],
        "duotone/thin" => ["duotone-thin", "duotone"],
        "sharp" => ["sharp-regular", "sharp-solid"],
        "sharp/light" => ["sharp-light", "sharp-solid"],
        "sharp/thin" => ["sharp-thin", "sharp-solid"],
        "sharp/duotone" => ["sharp-duotone-regular", "sharp-duotone-solid"],
        "sharp/duotone/light" => ["sharp-duotone-light", "sharp-duotone-solid"],
        "sharp/duotone/thin" => ["sharp-duotone-thin", "sharp-duotone-solid"],
    ];

    public function handle(): int
    {
        $token = trim((string) config("font-awesome-to-flux.token"));
        $package = $token !== ""
            ? "fontawesome-pro"
            : "fontawesome-free";
        $registry = $token !== ""
            ? self::PRO_REGISTRY
            : self::FREE_REGISTRY;
        $workDir = storage_path("framework/fontawesome-" . Str::random(16));

        if ($token === "") {
            $this->info("FONTAWESOME_NPM_TOKEN is not set, so FontAwesome Free is used.");
        }

        try {
            [$version, $svgPath] = $this->whileShowingStatus(
                "Downloading @fortawesome/{$package}...",
                fn () => $this->download(
                    package: $package,
                    registry: $registry,
                    token: $token,
                    workDir: $workDir,
                ),
            );

            $this->info("Using @fortawesome/{$package} {$version}.");

            File::deleteDirectory(resource_path("views/flux/icon/fontawesome"));
            $generated = $this->generateIcons($svgPath);
            $this->info("Generated {$generated} " . Str::plural("icon", $generated) . ".");
        } catch (ConnectionException) {
            $this->error("Could not connect to {$registry}.");

            return self::FAILURE;
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            File::deleteDirectory($workDir);
        }

        return self::SUCCESS;
    }

    protected function download(string $package, string $registry, string $token, string $workDir): array
    {
        $metadata = $this->registryRequest($token)
            ->accept("application/json")
            ->get("{$registry}/@fortawesome%2f{$package}");
        $this->ensureSuccessful(response: $metadata, package: $package, what: "package information");

        $version = (string) (config("font-awesome-to-flux.version") ?: $metadata->json("dist-tags.latest"));
        $dist = $metadata->json("versions")[$version]["dist"] ?? null;
        $tarballUrl = (string) data_get($dist, "tarball");
        $integrity = (string) data_get($dist, "integrity");

        if ($version === "" || ! is_array($dist)) {
            throw new RuntimeException("Version '{$version}' of @fortawesome/{$package} was not found in the registry.");
        }

        if (parse_url($tarballUrl, PHP_URL_SCHEME) !== "https"
            || parse_url($tarballUrl, PHP_URL_HOST) !== parse_url($registry, PHP_URL_HOST)
        ) {
            throw new RuntimeException("The registry listed a download for @fortawesome/{$package} {$version} that is not on " . parse_url($registry, PHP_URL_HOST) . ".");
        }

        File::ensureDirectoryExists($workDir);
        $tarballPath = "{$workDir}/package.tgz";
        $tarball = $this->registryRequest($token)->get($tarballUrl);
        $this->ensureSuccessful(response: $tarball, package: $package, what: "package download");
        File::put($tarballPath, $tarball->body());
        $this->verifyIntegrity(path: $tarballPath, integrity: $integrity, package: $package, version: $version);

        (new PharData($tarballPath))->extractTo($workDir);

        if (! is_dir("{$workDir}/package/svgs")) {
            throw new RuntimeException("@fortawesome/{$package} {$version} contains no svgs directory.");
        }

        return [$version, "{$workDir}/package/svgs"];
    }

    protected function registryRequest(string $token): PendingRequest
    {
        $request = Http::timeout(120);

        return $token !== ""
            ? $request->withToken($token)
            : $request;
    }

    protected function ensureSuccessful(Response $response, string $package, string $what): void
    {
        if (in_array($response->status(), [401, 403], true)) {
            throw new RuntimeException(
                "FontAwesome's registry rejected FONTAWESOME_NPM_TOKEN (HTTP {$response->status()}). "
                . "The token is missing, invalid, or revoked. Check the package token in your FontAwesome account.",
            );
        }

        if (! $response->successful()) {
            throw new RuntimeException("The {$what} request for @fortawesome/{$package} failed (HTTP {$response->status()}).");
        }
    }

    protected function verifyIntegrity(string $path, string $integrity, string $package, string $version): void
    {
        [$algorithm, $expected] = array_pad(explode("-", $integrity, 2), 2, "");

        if (! in_array($algorithm, ["sha512", "sha384", "sha256"], true)
            || ! hash_equals($expected, base64_encode(hash_file($algorithm, $path, true)))
        ) {
            throw new RuntimeException("The download of @fortawesome/{$package} {$version} failed its integrity check.");
        }
    }

    protected function showsLiveProgress(): bool
    {
        // Mirrors how Laravel decides whether Prompts may redraw the terminal. A CI or
        // deploy build has no TTY, so it gets plain lines instead of escape codes.
        return $this->input->isInteractive()
            && ($this->laravel->runningUnitTests() || (stream_isatty(STDIN) && stream_isatty(STDOUT)));
    }

    protected function whileShowingStatus(string $message, Closure $callback): mixed
    {
        if ($this->showsLiveProgress()) {
            return spin($callback, $message);
        }

        $this->line($message);

        return $callback();
    }

    protected function generateIcons(string $svgPath): int
    {
        $icons = collect(self::FAMILIES)
            ->flatMap(fn (array $variants, string $family) => $this->familyIcons($svgPath, $family, ...$variants))
            ->all();
        $stub = $this->loadStub();
        $write = fn (array $icon) => $this->writeIcon(icon: $icon, stub: $stub);

        if ($icons !== [] && $this->showsLiveProgress()) {
            return count(array_filter(progress(label: "Generating Flux icons", steps: $icons, callback: $write)));
        }

        $this->line("Generating " . count($icons) . " " . Str::plural("icon", count($icons)) . "...");

        return count(array_filter(array_map($write, $icons)));
    }

    protected function familyIcons(string $svgPath, string $family, string $outlineVariant, string $solidVariant): array
    {
        $familyPath = $family
            ? "/{$family}"
            : "";
        $outlineDir = "{$svgPath}/{$outlineVariant}";
        $solidDir = "{$svgPath}/{$solidVariant}";

        if (! is_dir($outlineDir) && ! is_dir($solidDir)) {
            return [];
        }

        $targetDir = resource_path("views/flux/icon/fontawesome{$familyPath}");
        File::ensureDirectoryExists($targetDir);

        return array_map(fn (string $iconName) => [
            "target" => "{$targetDir}/{$iconName}.blade.php",
            "outline" => "{$outlineDir}/{$iconName}.svg",
            "solid" => "{$solidDir}/{$iconName}.svg",
        ], $this->collectIconNames(outlineDir: $outlineDir, solidDir: $solidDir));
    }

    protected function writeIcon(array $icon, string $stub): bool
    {
        $outlineSvg = $this->extractSvgContent(iconPath: $icon["outline"]);
        $solidSvg = $this->extractSvgContent(iconPath: $icon["solid"]);
        $svgAttributes = $this->extractSvgAttributes(iconPath: $icon["outline"])
            ?: $this->extractSvgAttributes(iconPath: $icon["solid"]);

        $outlineSvg = $outlineSvg ?: $solidSvg;
        $solidSvg = $solidSvg ?: $outlineSvg;

        if (! $outlineSvg && ! $solidSvg) {
            return false;
        }

        file_put_contents($icon["target"], str_replace(
            ["{SVG_ATTRIBUTES}", "{OUTLINE}", "{SOLID}"],
            [$svgAttributes, $outlineSvg, $solidSvg],
            $stub,
        ));

        return true;
    }

    protected function loadStub(): string
    {
        $publishedStub = base_path("stubs/flux/icon.blade.php");
        $packageStub = __DIR__ . "/../../../stubs/flux/icon.blade.php";
        $stubPath = file_exists($publishedStub)
            ? $publishedStub
            : $packageStub;

        return file_get_contents($stubPath);
    }

    protected function collectIconNames(string $outlineDir, string $solidDir): array
    {
        $names = [];

        foreach ([$outlineDir, $solidDir] as $dir) {
            $svgFiles = glob($dir . "/*.svg") ?: [];

            foreach ($svgFiles as $svgFile) {
                $names[] = pathinfo($svgFile, PATHINFO_FILENAME);
            }
        }

        return array_unique($names);
    }

    protected function extractSvgContent(string $iconPath): string
    {
        if (! file_exists($iconPath)) {
            return "";
        }

        $svgContent = file_get_contents($iconPath);
        $matches = [];
        preg_match("/<svg[^>]*>(.*?)<\/svg>/is", $svgContent, $matches);

        return data_get($matches, 1) ?: "";
    }

    protected function extractSvgAttributes(string $iconPath): string
    {
        if (! file_exists($iconPath)) {
            return "";
        }

        $svgContent = file_get_contents($iconPath);
        $matches = [];
        preg_match("/<svg\s+([^>]*)>/i", $svgContent, $matches);

        return data_get($matches, 1) ?? "";
    }
}
