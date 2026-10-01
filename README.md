# Font Awesome to Flux Importer

Convert Font Awesome SVG icons into [Flux UI](https://fluxui.dev) Blade icon components — drop-in replacements for `<flux:icon>` with full variant support.

The command downloads the Font Awesome package itself. Your app does not need Node, `node_modules`, or an `.npmrc` for icons.

## Requirements

- PHP 8.2+ with the `phar` and `zlib` extensions (both are bundled with PHP)
- Laravel 11, 12, or 13
- [Flux UI](https://fluxui.dev) (Livewire component library)
- For Pro icons: a [Font Awesome Pro](https://fontawesome.com/plans) subscription and its npm package token

## Installation

### 1. Install the Composer package

```bash
composer require mike-bronner/flux-fontawesome-icons
```

The service provider is auto-discovered — no manual registration needed.

### 2. Configure the package (optional)

With no configuration, the command downloads [Font Awesome Free](https://www.npmjs.com/package/@fortawesome/fontawesome-free) (regular, solid, and brands) from the public npm registry. No login is needed.

To get Pro, set your Font Awesome npm package token:

```dotenv
FONTAWESOME_NPM_TOKEN=your-package-token
FONTAWESOME_VERSION=7.1.0
FONTAWESOME_CLASSIC_WEIGHT=regular
FONTAWESOME_SHARP_WEIGHT=light
FONTAWESOME_DUOTONE_WEIGHT=regular
FONTAWESOME_SHARP_DUOTONE_WEIGHT=thin
```

| Variable | Purpose |
|----------|---------|
| `FONTAWESOME_NPM_TOKEN` | Your Font Awesome npm package token. When it is set, the command downloads `@fortawesome/fontawesome-pro` from `npm.fontawesome.com`. When it is empty, it downloads Free. |
| `FONTAWESOME_VERSION` | The package version to download. When it is empty, the latest version is used. |
| `FONTAWESOME_CLASSIC_WEIGHT` | The outline weight of the classic family: `regular`, `light`, or `thin`. |
| `FONTAWESOME_SHARP_WEIGHT` | The outline weight of the sharp family. Pro only. |
| `FONTAWESOME_DUOTONE_WEIGHT` | The outline weight of the duotone family. Pro only. |
| `FONTAWESOME_SHARP_DUOTONE_WEIGHT` | The outline weight of the sharp duotone family. Pro only. |

To publish the config file to `config/font-awesome-to-flux.php`:

```bash
php artisan vendor:publish --tag=font-awesome-to-flux-config
```

There is no command-line option for the token. A token on the command line lands in your shell history and in process lists.

### 3. Choose the outline weight of each family

The command generates every family in the package on every run: classic, sharp, duotone, and sharp duotone, plus brands. Each family gets one outline weight, `regular`, `light`, or `thin`. That weight becomes the family's Flux `outline` variant. The `solid` variant is always the family's own solid style, so there is nothing to choose for it.

When a family has no configured weight:

- In a terminal, the command asks for the weight of that family, after the download.
- Without an interactive terminal, as in CI or with `--no-interaction`, the command uses `regular`. It never waits for input.

Set a weight for every family in CI, so every build generates the same icons.

The `light` and `thin` weights and every family other than classic need Pro. Without `FONTAWESOME_NPM_TOKEN`:

- The command generates classic (regular and solid) and brands only, and asks nothing.
- It skips the sharp, duotone, and sharp duotone families, even when their weights are set.
- It stops with an error and a non-zero exit when `FONTAWESOME_CLASSIC_WEIGHT` is `light` or `thin`. It stops before the download, and the existing icons stay in place.

A weight other than `regular`, `light`, or `thin` stops the command with an error that lists the accepted values.

## Keeping the token secret

**Where the token comes from.** It is the npm package token from your Font Awesome account, the same token that goes in an `.npmrc` for the Pro npm package. Find it on your [Font Awesome account page](https://fontawesome.com/account). It is not a Font Awesome API token.

**Where the token goes.**

- Locally, put it in `.env`.
- In CI and on deploy servers, store it as a secret, and expose it to the build as the `FONTAWESOME_NPM_TOKEN` environment variable.

**What never gets committed.** Never commit `.env` or an `.npmrc` that holds the token. Check that both are in your `.gitignore`. This package does not need an `.npmrc` at all, so you can delete one that exists only for Font Awesome.

**If the token was ever committed, rotate it.** Removing the file in a later commit does not help, because the token stays in the git history. Generate a new token in your Font Awesome account, and update `.env` and your CI secrets.

## Pin the version and generate the icons in your build

The generated icons are build output. Do not commit them. Add the folder to `.gitignore`:

```gitignore
/resources/views/flux/icon/fontawesome/
```

Then run the command in every build, before the views are cached:

```bash
php artisan flux:import-fontawesome
```

Set `FONTAWESOME_VERSION` so every build generates the same icons. Without a pinned version, a build that runs after a Font Awesome release gets the new release.

## Usage

Run the Artisan command to generate Flux-compatible Blade icon components:

```bash
php artisan flux:import-fontawesome
```

The command downloads the package, states which package and version it used, converts every SVG into a Blade component, and writes them to `resources/views/flux/icon/fontawesome/`.

In a terminal, the command shows a spinner during the download, asks for each family weight that is not configured, and shows a progress bar while it generates the icons. Without an interactive terminal, as in CI, or with `--no-interaction`, it asks nothing and prints one plain line per step. Either way, it ends with the number of icons it generated.

If the registry rejects the token, the command says so and exits with a non-zero status. It never prints the token.

### Using the icons

Once generated, use them anywhere you'd use a Flux icon:

```blade
{{-- Default (outline) variant --}}
<flux:icon.fontawesome.heart />

{{-- Solid variant --}}
<flux:icon.fontawesome.heart variant="solid" />

{{-- Brands --}}
<flux:icon.fontawesome.brands.github />

{{-- Pro families (requires FONTAWESOME_NPM_TOKEN), each in its configured weight --}}
<flux:icon.fontawesome.sharp.heart />
<flux:icon.fontawesome.duotone.heart />
<flux:icon.fontawesome.sharp.duotone.heart />
```

### Available variants

Each icon supports Flux's standard `variant` prop:

| Variant | Size | Description |
|---------|------|-------------|
| `outline` | 24px | Default — uses the family's configured weight |
| `solid` | 24px | Filled version |
| `mini` | 20px | Smaller outline |
| `micro` | 16px | Smallest outline |

### Icon families

| Family | Path | Outline | Solid | Package |
|--------|------|---------|-------|---------|
| Classic | `fontawesome.{icon}` | `FONTAWESOME_CLASSIC_WEIGHT` | solid | Free (regular only) / Pro |
| Sharp | `fontawesome.sharp.{icon}` | `FONTAWESOME_SHARP_WEIGHT` | sharp-solid | Pro |
| Duotone | `fontawesome.duotone.{icon}` | `FONTAWESOME_DUOTONE_WEIGHT` | duotone | Pro |
| Sharp Duotone | `fontawesome.sharp.duotone.{icon}` | `FONTAWESOME_SHARP_DUOTONE_WEIGHT` | sharp-duotone-solid | Pro |
| Brands | `fontawesome.brands.{icon}` | brands | brands | Free / Pro |

### Breaking change: the weight paths are gone

Earlier versions generated a separate path for every weight, such as `fontawesome.light.{icon}`, `fontawesome.thin.{icon}`, `fontawesome.duotone.light.{icon}`, and `fontawesome.sharp.thin.{icon}`. These paths are no longer generated. Each family now has one path, and its configured weight sets the outline variant.

To upgrade, set the weight of each family, then replace every weight path in your Blade views with its family path. For example, set `FONTAWESOME_CLASSIC_WEIGHT=light`, and replace `<flux:icon.fontawesome.light.heart />` with `<flux:icon.fontawesome.heart />`. A family can use only one weight at a time.

## Customizing the Stub

The generated Blade components use a stub template. To customize it:

```bash
php artisan vendor:publish --tag=font-awesome-to-flux-stubs
```

This publishes the stub to `stubs/flux/icon.blade.php` in your project root. The command will use your published version over the package default.

## Updating Icons

Change `FONTAWESOME_VERSION` to the new version, and re-run the command:

```bash
php artisan flux:import-fontawesome
```

The command clears the `resources/views/flux/icon/fontawesome/` directory before regenerating, so you always get a clean set. If the download fails, the existing icons stay in place.

## How It Works

1. Picks the package: Pro when `FONTAWESOME_NPM_TOKEN` is set, Free when it is not
2. Reads the package information from the registry, and picks the pinned version or the latest one
3. Downloads the package, checks it against the registry's integrity hash, and unpacks it to a temporary folder
4. Picks the outline weight of each family from the config, a prompt, or the `regular` default
5. Iterates through each family's outline and solid directories, and the brands directory
6. Extracts SVG inner content and attributes from the source `.svg` files
7. Injects them into a Blade stub that supports Flux's `variant` prop
8. Writes the generated components to `resources/views/flux/icon/fontawesome/`, and deletes the temporary folder

## License

MIT — see [LICENSE](LICENSE) for details.
