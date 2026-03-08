<?php

declare(strict_types=1);

namespace MikeBronner\FontAwesomeToFluxImporter\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ImportCommand extends Command
{
    // phpcs:disable SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingAnyTypeHint
    protected $signature = "flux:import-fontawesome";
    protected $description = "Convert FontAwesome SVG icons to Flux icons";
    // phpcs:enable

    public function handle(): void
    {
        $package = $this->resolvePackage();

        if (! $package) {
            $this->error("No FontAwesome package found in node_modules/@fortawesome/.");

            return;
        }

        $this->info("Using @fortawesome/{$package}.");

        Storage::deleteDirectory("resources/views/flux/icon/fontawesome");
        $this->processIcons($package, "", "regular", "solid");
        $this->processIcons($package, "brands", "brands", "brands");
        $this->processIcons($package, "light", "light", "solid");
        $this->processIcons($package, "thin", "thin", "solid");
        $this->processIcons($package, "duotone", "duotone-regular", "duotone");
        $this->processIcons($package, "duotone/light", "duotone-light", "duotone");
        $this->processIcons($package, "duotone/thin", "duotone-thin", "duotone");
        $this->processIcons($package, "sharp", "sharp-regular", "sharp-solid");
        $this->processIcons($package, "sharp/light", "sharp-light", "sharp-solid");
        $this->processIcons($package, "sharp/thin", "sharp-thin", "sharp-solid");
        $this->processIcons($package, "sharp/duotone", "sharp-duotone-regular", "sharp-duotone-solid");
        $this->processIcons($package, "sharp/duotone/light", "sharp-duotone-light", "sharp-duotone-solid");
        $this->processIcons($package, "sharp/duotone/thin", "sharp-duotone-thin", "sharp-duotone-solid");
    }

    protected function resolvePackage(): string
    {
        $proPath = base_path("node_modules/@fortawesome/fontawesome-pro");
        $freePath = base_path("node_modules/@fortawesome/fontawesome-free");

        if (is_dir($proPath)) {
            return "fontawesome-pro";
        }

        if (is_dir($freePath)) {
            return "fontawesome-free";
        }

        return "";
    }

    protected function processIcons(
        string $package,
        string $family,
        string $outlineVariant,
        string $solidVariant,
    ): void {
        $familyPath = $family
            ? "/{$family}"
            : "";
        $outlineDir = base_path("node_modules/@fortawesome/{$package}/svgs/{$outlineVariant}");
        $solidDir = base_path("node_modules/@fortawesome/{$package}/svgs/{$solidVariant}");

        if (! is_dir($outlineDir) && ! is_dir($solidDir)) {
            return;
        }

        $targetDir = resource_path("views/flux/icon/fontawesome{$familyPath}");
        $stub = $this->loadStub();

        if (! file_exists($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $iconNames = $this->collectIconNames(outlineDir: $outlineDir, solidDir: $solidDir);

        foreach ($iconNames as $iconName) {
            $targetFile = "{$targetDir}/{$iconName}.blade.php";

            $outlineSvg = $this->extractSvgContent(package: $package, variant: $outlineVariant, iconName: $iconName);
            $solidSvg = $this->extractSvgContent(package: $package, variant: $solidVariant, iconName: $iconName);
            $svgAttributes = $this->extractSvgAttributes(package: $package, variant: $outlineVariant, iconName: $iconName)
                ?: $this->extractSvgAttributes(package: $package, variant: $solidVariant, iconName: $iconName);

            $outlineSvg = $outlineSvg ?: $solidSvg;
            $solidSvg = $solidSvg ?: $outlineSvg;

            if (! $outlineSvg && ! $solidSvg) {
                continue;
            }

            $bladeContent = str_replace(
                ["{SVG_ATTRIBUTES}", "{OUTLINE}", "{SOLID}"],
                [$svgAttributes, $outlineSvg, $solidSvg],
                $stub,
            );

            file_put_contents($targetFile, $bladeContent);
            $this->info("Converted: {$targetFile}");
        }

        $this->info("Converted {$family} icons successfully.");
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

    protected function extractSvgContent(string $package, string $variant, string $iconName): string
    {
        $iconPath = base_path("node_modules/@fortawesome/{$package}/svgs/{$variant}/{$iconName}.svg");

        if (! file_exists($iconPath)) {
            return "";
        }

        $svgContent = file_get_contents($iconPath);
        $matches = [];
        preg_match("/<svg[^>]*>(.*?)<\/svg>/is", $svgContent, $matches);

        return data_get($matches, 1) ?: "";
    }

    protected function extractSvgAttributes(string $package, string $variant, string $iconName): string
    {
        $iconPath = base_path("node_modules/@fortawesome/{$package}/svgs/{$variant}/{$iconName}.svg");

        if (! file_exists($iconPath)) {
            return "";
        }

        $svgContent = file_get_contents($iconPath);
        $matches = [];
        preg_match("/<svg\s+([^>]*)>/i", $svgContent, $matches);

        return data_get($matches, 1) ?? "";
    }
}
