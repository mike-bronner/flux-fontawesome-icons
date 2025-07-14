<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ConvertFontAwesomeToFluxCommand extends Command
{
    // phpcs:disable SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingAnyTypeHint
    protected $signature = "flux:convert-fontawesome";
    protected $description = "Convert FontAwesome SVG icons to Flux icons";
    // phpcs:enable

    public function handle(): void
    {
        Storage::deleteDirectory("resources/views/flux/icon/fontawesome");
        $this->processIcons("", "regular", "solid");
        $this->processIcons("brands", "brands", "brands");
        // $this->processIcons("light", "light", "solid");
        // $this->processIcons("thin", "thin", "solid");
        // $this->processIcons( "duotone", "duotone-regular", "duotone");
        // $this->processIcons( "duotone/light", "duotone-light", "duotone");
        // $this->processIcons( "duotone/thin", "duotone-thin", "duotone");
        // $this->processIcons("sharp", "sharp-regular", "sharp-solid");
        // $this->processIcons("sharp/light", "sharp-light", "sharp-solid");
        // $this->processIcons("sharp/thin", "sharp-thin", "sharp-solid");
        // $this->processIcons( "sharp/duotone", "sharp-duotone-regular", "sharp-duotone-solid");
        // $this->processIcons( "sharp/duotone/light", "sharp-duotone-light", "sharp-duotone-solid");
        // $this->processIcons( "sharp/duotone/thin", "sharp-duotone-thin", "sharp-duotone-solid");
    }

    public function processIcons(
        string $family,
        string $outlineVariant,
        string $solidVariant,
    ): void {
        $family = $family
            ? "/{$family}"
            : "";
        $sourceDirs = [
            base_path("node_modules/@fortawesome/fontawesome-free/svgs/{$outlineVariant}"),
            base_path("node_modules/@fortawesome/fontawesome-free/svgs/{$solidVariant}"),
        ];
        $targetDir = resource_path("views/flux/icon/fontawesome{$family}");
        $stub = file_get_contents(base_path("stubs/flux/icon.blade.php"));

        if (! file_exists($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        foreach ($sourceDirs as $sourceDir) {
            $svgFiles = glob($sourceDir . "/*.svg");

            foreach ($svgFiles as $svgFile) {
                $iconName = pathinfo($svgFile, PATHINFO_FILENAME);
                $targetFile = "{$targetDir}/{$iconName}.blade.php";

                $svgContent = file_get_contents($svgFile);

                $svgAttributes = $this->parseSvgAttributes($svgContent);
                $solidInnerSvg = $this->parseSvgContent($sourceDir, $solidVariant, $iconName);
                $regularInnerSvg = $this->parseSvgContent($sourceDir, $outlineVariant, $svgContent)
                    ?: $solidInnerSvg;
// dd($sourceDir, $solidInnerSvg, $regularInnerSvg);
                $bladeContent = str_replace(
                    ['{SVG_ATTRIBUTES}', '{OUTLINE}', '{SOLID}'],
                    [$svgAttributes, $regularInnerSvg, $solidInnerSvg],
                    $stub,
                );
                file_put_contents($targetFile, $bladeContent);

                $this->info("Converted: {$targetFile}");
            }
        }

        $this->info("All FontAwesome SVGs have been converted to Flux Blade icons.");
    }

    protected function parseSvgAttributes(string $svgContent): string
    {
        $attrMatch = [];
        preg_match('/<svg\s+([^>]*)>/i', $svgContent, $attrMatch);

        return data_get($attrMatch, 1)
            ?? "";
    }

    // protected function parseRegularSvgContent(string $svgContent): string
    // {
    //     $innerMatch = [];
    //     preg_match('/<svg[^>]*>(.*?)<\/svg>/is', $svgContent, $innerMatch);

    //     return data_get($innerMatch, 1)
    //         ?: "";
    // }

    protected function parseSvgContent(string $path, string $variant, string $iconName): string
    {
        $matches = [];
        $iconPath = base_path("node_modules/@fortawesome/fontawesome-free/svgs/{$variant}/{$iconName}.svg");
        $innerSvg = "";

        if (file_exists($iconPath)) {
            $svgContent = file_get_contents($iconPath);
            preg_match('/<svg[^>]*>(.*?)<\/svg>/is', $svgContent, $matches);
            $innerSvg = data_get($matches, 1)
                ?: "";
        }

        return $innerSvg;
    }

    // protected function parseSolidSvgContent(string $path, string $solidVariant, string $iconName): string
    // {
    //     $solidIconPath = base_path(
    //         "node_modules/@fortawesome/fontawesome-pro/svgs/{$solidVariant}/{$iconName}.svg",
    //     );
    //     $solidInnerSvg = "";

    //     if (file_exists($solidIconPath)) {
    //         $solidSvgContent = file_get_contents($solidIconPath);
    //         preg_match('/<svg[^>]*>(.*?)<\/svg>/is', $solidSvgContent, $solidMatch);
    //         $solidInnerSvg = data_get($solidMatch, 1)
    //             ?: "";
    //     }

    //     return $solidInnerSvg;
    // }
}
