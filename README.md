# Font Awesome to Flux Importer

Convert Font Awesome SVG icons into [Flux UI](https://fluxui.dev) Blade icon components — drop-in replacements for `<flux:icon>` with full variant support.

## Requirements

- PHP 8.2+
- Laravel 11, 12, or 13
- [Flux UI](https://fluxui.dev) (Livewire component library)
- Font Awesome [Free](https://www.npmjs.com/package/@fortawesome/fontawesome-free) or [Pro](https://fontawesome.com/plans) npm package

## Installation

### 1. Install the Composer package

```bash
composer require genealabs/font-awesome-to-flux-importer
```

The service provider is auto-discovered — no manual registration needed.

### 2. Install Font Awesome via npm

**Pro** (recommended — includes all icon families and styles):

```bash
npm install --save @fortawesome/fontawesome-pro
```

**Free** (regular, solid, and brands only):

```bash
npm install --save @fortawesome/fontawesome-free
```

> If both packages are installed, only Pro is used — it's a strict superset of Free.

## Usage

Run the Artisan command to generate Flux-compatible Blade icon components:

```bash
php artisan flux:import-fontawesome
```

This scans your `node_modules/@fortawesome` directory, converts every SVG into a Blade component, and writes them to `resources/views/flux/icon/fontawesome/`.

### Using the icons

Once generated, use them anywhere you'd use a Flux icon:

```blade
{{-- Default (outline) variant --}}
<flux:icon.fontawesome.heart />

{{-- Solid variant --}}
<flux:icon.fontawesome.heart variant="solid" />

{{-- Brands --}}
<flux:icon.fontawesome.brands.github />

{{-- Pro families (requires Pro package) --}}
<flux:icon.fontawesome.light.heart />
<flux:icon.fontawesome.thin.heart />
<flux:icon.fontawesome.duotone.heart />
<flux:icon.fontawesome.sharp.heart />
<flux:icon.fontawesome.sharp.duotone.heart />
```

### Available variants

Each icon supports Flux's standard `variant` prop:

| Variant | Size | Description |
|---------|------|-------------|
| `outline` | 24px | Default — uses the regular/outline SVG |
| `solid` | 24px | Filled version |
| `mini` | 20px | Smaller outline |
| `micro` | 16px | Smallest outline |

### Icon families

| Family | Path | Package |
|--------|------|---------|
| Regular + Solid | `fontawesome.{icon}` | Free / Pro |
| Brands | `fontawesome.brands.{icon}` | Free / Pro |
| Light | `fontawesome.light.{icon}` | Pro |
| Thin | `fontawesome.thin.{icon}` | Pro |
| Duotone | `fontawesome.duotone.{icon}` | Pro |
| Duotone Light | `fontawesome.duotone.light.{icon}` | Pro |
| Duotone Thin | `fontawesome.duotone.thin.{icon}` | Pro |
| Sharp | `fontawesome.sharp.{icon}` | Pro |
| Sharp Light | `fontawesome.sharp.light.{icon}` | Pro |
| Sharp Thin | `fontawesome.sharp.thin.{icon}` | Pro |
| Sharp Duotone | `fontawesome.sharp.duotone.{icon}` | Pro |
| Sharp Duotone Light | `fontawesome.sharp.duotone.light.{icon}` | Pro |
| Sharp Duotone Thin | `fontawesome.sharp.duotone.thin.{icon}` | Pro |

## Customizing the Stub

The generated Blade components use a stub template. To customize it:

```bash
php artisan vendor:publish --tag=font-awesome-to-flux-stubs
```

This publishes the stub to `stubs/flux/icon.blade.php` in your project root. The command will use your published version over the package default.

## Updating Icons

After updating your Font Awesome npm package, re-run the command:

```bash
npm update @fortawesome/fontawesome-pro
php artisan flux:import-fontawesome
```

The command clears the `resources/views/flux/icon/fontawesome/` directory before regenerating, so you always get a clean set.

## How It Works

1. Detects which Font Awesome package is installed (Pro takes priority over Free)
2. Iterates through each icon family and variant directory
3. Extracts SVG inner content and attributes from the source `.svg` files
4. Injects them into a Blade stub that supports Flux's `variant` prop
5. Writes the generated components to `resources/views/flux/icon/fontawesome/`

## License

MIT — see [LICENSE](LICENSE) for details.
