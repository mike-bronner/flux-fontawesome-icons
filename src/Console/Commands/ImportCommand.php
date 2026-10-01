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
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

class ImportCommand extends Command
{
    // phpcs:disable SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingAnyTypeHint
    protected $signature = "flux:import-fontawesome";
    protected $description = "Download FontAwesome and convert its SVG icons to Flux icons";
    // phpcs:enable

    protected const PRO_REGISTRY = "https://npm.fontawesome.com";
    protected const FREE_REGISTRY = "https://registry.npmjs.org";

    // Each family maps to its icon path, the prefix of its outline weight directories,
    // and its solid directory in the package.
    protected const FAMILIES = [
        "classic" => [
            "path" => "",
            "outline" => "",
            "solid" => "solid",
        ],
        "sharp" => [
            "path" => "sharp",
            "outline" => "sharp-",
            "solid" => "sharp-solid",
        ],
        "duotone" => [
            "path" => "duotone",
            "outline" => "duotone-",
            "solid" => "duotone",
        ],
        "sharp_duotone" => [
            "path" => "sharp/duotone",
            "outline" => "sharp-duotone-",
            "solid" => "sharp-duotone-solid",
        ],
    ];
    protected const WEIGHTS = ["regular", "light", "thin"];
    protected const DEFAULT_WEIGHT = "regular";

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
            $configuredWeights = $this->configuredWeights();
            $this->ensureFreeHasWeight(token: $token, weight: data_get($configuredWeights, "classic"));

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

            $weights = $this->chosenWeights(svgPath: $svgPath, configuredWeights: $configuredWeights);

            File::deleteDirectory(resource_path("views/flux/icon/fontawesome"));
            $generated = $this->generateIcons(svgPath: $svgPath, weights: $weights);
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

    protected function configuredWeights(): array
    {
        return collect(self::FAMILIES)
            ->keys()
            ->mapWithKeys(fn (string $family) => [$family => $this->configuredWeight($family)])
            ->all();
    }

    protected function configuredWeight(string $family): string
    {
        $weight = trim((string) config("font-awesome-to-flux.weights.{$family}"));

        if (
            $weight !== ""
            && ! collect(self::WEIGHTS)->contains($weight)
        ) {
            throw new RuntimeException(
                "FONTAWESOME_" . Str::upper($family) . "_WEIGHT is set to '{$weight}'. "
                    . "Use one of: " . collect(self::WEIGHTS)->implode(", ") . ".",
            );
        }

        return $weight;
    }

    protected function ensureFreeHasWeight(string $token, string $weight): void
    {
        if (
            $token === ""
            && ! collect(["", self::DEFAULT_WEIGHT])->contains($weight)
        ) {
            throw new RuntimeException(
                "FONTAWESOME_CLASSIC_WEIGHT is set to '{$weight}', but Font Awesome Free has only "
                    . "the regular weight. Set FONTAWESOME_NPM_TOKEN to use the {$weight} weight.",
            );
        }
    }

    protected function chosenWeights(string $svgPath, array $configuredWeights): array
    {
        return collect($configuredWeights)
            ->map(fn (string $weight, string $family) => $weight
                ?: $this->askedWeight(svgPath: $svgPath, family: $family))
            ->all();
    }

    protected function askedWeight(string $svgPath, string $family): string
    {
        $outlinePrefix = data_get(self::FAMILIES, "{$family}.outline");
        $availableWeights = collect(self::WEIGHTS)
            ->filter(fn (string $weight) => is_dir("{$svgPath}/{$outlinePrefix}{$weight}"));

        if (
            $availableWeights->count() <= 1
            || ! $this->hasInteractiveTerminal()
        ) {
            return self::DEFAULT_WEIGHT;
        }

        return select(
            label: "Which weight should the " . Str::headline($family) . " outline variant use?",
            options: $availableWeights
                ->mapWithKeys(fn (string $weight) => [$weight => Str::headline($weight)])
                ->all(),
            default: self::DEFAULT_WEIGHT,
        );
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

    protected function hasInteractiveTerminal(): bool
    {
        // Mirrors how Laravel decides whether Prompts may redraw the terminal. A CI or
        // deploy build has no TTY, so it gets no prompts and plain lines instead of escape codes.
        return $this->input->isInteractive()
            && ($this->laravel->runningUnitTests() || (stream_isatty(STDIN) && stream_isatty(STDOUT)));
    }

    protected function whileShowingStatus(string $message, Closure $callback): mixed
    {
        if ($this->hasInteractiveTerminal()) {
            return spin($callback, $message);
        }

        $this->line($message);

        return $callback();
    }

    protected function generateIcons(string $svgPath, array $weights): int
    {
        $icons = collect($weights)
            ->mapWithKeys(fn (string $weight, string $family) => [
                data_get(self::FAMILIES, "{$family}.path") => [
                    data_get(self::FAMILIES, "{$family}.outline") . $weight,
                    data_get(self::FAMILIES, "{$family}.solid"),
                ],
            ])
            ->put("brands", ["brands", "brands"])
            ->flatMap(fn (array $variants, string $path) => $this->familyIcons(
                $svgPath,
                $path,
                ...$variants,
            ))
            ->all();
        $stub = $this->loadStub();
        $write = fn (array $icon) => $this->writeIcon(icon: $icon, stub: $stub);

        if ($icons !== [] && $this->hasInteractiveTerminal()) {
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
