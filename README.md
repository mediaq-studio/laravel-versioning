# Laravel Versioning

`npm version`, but for Composer. One command bumps the version in `composer.json`, and it only does
git work when you ask for it.

- `composer.json` is the single source of truth for the application version.
- Nothing is committed, tagged or pushed unless you pass `--commit`, `--tag` or `--push`.
- Only the version line is rewritten, so the rest of `composer.json` keeps its formatting.
- The result is validated as JSON before it is written.

## Requirements

- PHP 8.2+
- Laravel 11 or 12

## Installation

In a project that has this package in a `packages/` folder (like this repository), add it to
`composer.json`:

```json
{
    "repositories": [
        { "type": "path", "url": "packages/laravel-versioning" }
    ]
}
```

```bash
composer require mediaq-studio/laravel-versioning --dev
```

The service provider is auto-discovered. Publish the config when you want to change the defaults:

```bash
php artisan vendor:publish --tag=versioning-config
```

> Working inside the host application without `composer require`? Map the namespace in the
> `autoload.psr-4` section of the root `composer.json` and run `composer dump-autoload`:
> `"MediaQ\\Versioning\\": "packages/laravel-versioning/src/"`.

## Usage

```bash
php artisan version                # show the current version
php artisan version patch          # 1.0.1 -> 1.0.2
php artisan version minor          # 1.0.1 -> 1.1.0
php artisan version major          # 1.0.1 -> 2.0.0
php artisan version prepatch       # 1.0.1 -> 1.0.2-beta.0
php artisan version preminor       # 1.0.1 -> 1.1.0-beta.0
php artisan version premajor       # 1.0.1 -> 2.0.0-beta.0
php artisan version prerelease     # 1.0.1-beta.1 -> 1.0.1-beta.2
php artisan version 2.1.5          # set an exact version
```

### Options

| Option          | Meaning                                                                         |
| --------------- | ------------------------------------------------------------------------------- |
| `--show`        | Print the current version and stop                                               |
| `--dry-run`     | Print the planned file change and git commands without doing anything            |
| `--preid=`      | Pre-release identifier for `prerelease`, `prepatch`, `preminor` and `premajor`    |
| `--commit`      | Commit the change (`git add` only stages the composer file)                      |
| `--tag`         | Create an annotated tag `v<version>`, implies `--commit`                         |
| `--push`        | Push the commit and the tag, implies `--commit` and `--tag`                      |
| `--allow-dirty` | Allow committing while the working tree has other uncommitted changes            |
| `--message=`    | Commit message, `:version` is replaced with the new version                      |

Git preflight: a dirty working tree or an already existing tag aborts the run before anything is
written, unless `--allow-dirty` is passed.

### Typical release

```bash
php artisan version patch                       # bump only, review the diff
php artisan version patch --commit --tag        # bump and create the release commit + tag
php artisan version patch --commit --tag --push # same, and push the branch and the tag
```

## Semantics

The bump rules match `npm version`:

| Current         | `patch` | `minor` | `major` |
| --------------- | ------- | ------- | ------- |
| `1.2.3`         | `1.2.4` | `1.3.0` | `2.0.0` |
| `1.2.3-beta.1`  | `1.2.3` | `1.3.0` | `2.0.0` |
| `1.2.0-beta.1`  | `1.2.0` | `1.2.0` | `2.0.0` |
| `2.0.0-beta.1`  | `2.0.0` | `2.0.0` | `3.0.0` |

`prerelease` increments the pre-release number (`1.2.3-beta.1` → `1.2.3-beta.2`) and restarts at `.0`
when the identifier changes or the pre-release is not in `identifier.number` form.

## Reading the version in the application

```php
'version' => (new MediaQ\Versioning\VersionFile(base_path('composer.json')))->read()?->toString(),
```

`read()` returns `null` when the file, the field or the format is unusable, so guard for that.

## Configuration

| Key                     | Default                    |
| ----------------------- | -------------------------- |
| `composer_json`         | `composer.json`            |
| `preid`                 | `beta`                     |
| `commit_message`        | `chore(release): :version` |
| `tag_prefix`            | `v`                        |
| `remote`                | `origin`                   |

## Tests

```bash
vendor/bin/phpunit -c packages/laravel-versioning/phpunit.xml
```

The bootstrap reuses the host application autoloader, so the package does not need its own
`vendor/` directory.

## Maintained by

This repository is maintained by [MediaQ Creative Digital Solutions](https://mediaq.nl).
