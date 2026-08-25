<?php

declare(strict_types=1);

// This bundle's own composer.json requires "pimcore/pimcore", but its fixtures (e.g. FakeProduct
// extends the host app's generated Pimcore\Model\DataObject\Product) need classes that only exist
// once the host app's class definitions have been built — a standalone `composer install` in this
// repo pulls Pimcore core but never generates those classes. So the host app's autoloader (this
// bundle mounted as a Composer path repository inside a Pimcore container, see the host app's
// docs/LOCAL_BUNDLE_DEVELOPMENT.md: this repo at /var/www/pimcore-data-quality-bundle-studio, host app at
// /var/www/html) is the only autoloader that can actually run the full suite, and must be
// preferred. A standalone local vendor/ is kept as a fallback only for contexts that don't need
// host-generated classes (e.g. static analysis tooling), not as a supported way to run the tests.
$candidates = [
    '/var/www/html/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
];

foreach ($candidates as $candidate) {
    if (\is_file($candidate)) {
        $loader = require $candidate;

        // When this bundle is loaded as a Composer *dependency* of the host app (the
        // /var/www/html/vendor/autoload.php fallback above), Composer only wires up the root
        // package's autoload-dev — this bundle's own "Portadesign\DataQualityBundle\Tests\" mapping
        // is silently skipped. Register it manually so the test suite still works unmodified
        // whether run standalone or from inside the host container.
        if ($loader instanceof \Composer\Autoload\ClassLoader) {
            $loader->addPsr4('Portadesign\\DataQualityBundle\\Tests\\', __DIR__ . '/');
        }

        return;
    }
}

throw new \RuntimeException(
    'Could not locate a Composer autoloader. Run "composer install" in this repo, or run the ' .
    'test suite from inside the host Pimcore app container where this bundle is mounted.'
);
