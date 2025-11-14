<?php

declare(strict_types=1);

use Isolated\Symfony\Component\Finder\Finder;

// You can do your own things here, e.g. collecting symbols to expose dynamically
// or files to exclude.
// However beware that this file is executed by PHP-Scoper, hence if you are using
// the PHAR it will be loaded by the PHAR. So it is highly recommended to avoid
// to auto-load any code here: it can result in a conflict or even corrupt
// the PHP-Scoper analysis.

// Example of collecting files to include in the scoped build but to not scope
// leveraging the isolated finder.
// $excludedFiles = array_map(
//     static fn (SplFileInfo $fileInfo) => $fileInfo->getPathName(),
//     iterator_to_array(
//         Finder::create()->files()->in(__DIR__),
//         false,
//     ),
// );


// soit define brut, soit réutilisation de la fonction normale
// define('__ZUGFERDPACKAGEVERSION__',"['1.0.1']");
require('vendor/composer/InstalledVersions.php');
require('vendor/horstoeko/zugferd/src/ZugferdPackageVersion.php');
use horstoeko\zugferd\ZugferdPackageVersion;
define('__ZUGFERDPACKAGEVERSION_ARR__',"['" . ZugferdPackageVersion::getInstalledVersion() . "']");
define('__ZUGFERDPACKAGEVERSION__',"'" . ZugferdPackageVersion::getInstalledVersion() . "'");

return [
    // The prefix configuration. If a non-null value is used, a random prefix
    // will be generated instead.
    //
    // For more see: https://github.com/humbug/php-scoper/blob/master/docs/configuration.md#prefix
    'prefix' => "custom\\facturx",

    // The base output directory for the prefixed files.
    // This will be overridden by the 'output-dir' command line option if present.
    'output-dir' => "/tmp/facturx-scoper",

    // By default when running php-scoper add-prefix, it will prefix all relevant code found in the current working
    // directory. You can however define which files should be scoped by defining a collection of Finders in the
    // following configuration key.
    //
    // This configuration entry is completely ignored when using Box.
    //
    // For more see: https://github.com/humbug/php-scoper/blob/master/docs/configuration.md#finders-and-paths
    'finders' => [
        Finder::create()->files()->exclude([
            'build',
            'test',
            'tools',
            'vendor-bin',
        ])->in('.'),
        Finder::create()
            ->files()
            ->ignoreVCS(true)
            ->notName('/LICENSE|.*\\.md|.*\\.dist|Makefile|composer\\.json|composer\\.lock/')
            ->exclude([
                'doc',
                'test',
                'test_old',
                'tests',
                'Tests',
                'vendor-bin',
            ])
            ->in('vendor'),
        Finder::create()->append([
            'composer.json',
        ]),
    ],

    // List of excluded files, i.e. files for which the content will be left untouched.
    // Paths are relative to the configuration file unless if they are already absolute
    //
    // For more see: https://github.com/humbug/php-scoper/blob/master/docs/configuration.md#patchers
    'exclude-files' => [
        // 'src/an-excluded-file.php',
        // ...$excludedFiles,
        'class/actions_facturx.class.php',
		'core/triggers/interface_99_modFacturx_FacturxTriggers.class.php',
		'vendor/horstoeko/zugferd/src/ZugferdPackageVersion.php'
    ],

    // When scoping PHP files, there will be scenarios where some of the code being scoped indirectly references the
    // original namespace. These will include, for example, strings or string manipulations. PHP-Scoper has limited
    // support for prefixing such strings. To circumvent that, you can define patchers to manipulate the file to your
    // heart contents.
    //
    // For more see: https://github.com/humbug/php-scoper/blob/master/docs/configuration.md#patchers
    'patchers' => [
        static function (string $filePath, string $prefix, string $contents): string {
			//cas particulier pour "sortir" de phpscoper et utiliser composer normal -> ne marche pas on remplace donc "en dur" lors du scopper
			$contents = str_replace("ZugferdPackageVersion::getInstalledVersion()", __ZUGFERDPACKAGEVERSION_ARR__ , $contents);
			$contents = str_replace("ComposerInstalledVersions::getVersion('horstoeko/zugferd')", __ZUGFERDPACKAGEVERSION__ , $contents);
            $contents = str_replace("createClassInstance('".addslashes($prefix)."\\\\", "createClassInstance('", $contents);
            $contents = str_replace("sprintf('horstoeko\\\\zugferd\\\\entities", "sprintf('".$prefix."\\\\horstoeko\\\\zugferd\\\\entities", $contents);
            $contents = str_replace("className = 'horstoeko\\\zugferd\\\\entities", "className = '".$prefix."\\\\horstoeko\\\\zugferd\\\\entities", $contents);
            $contents = str_replace("DEFAULT_NAMESPACE = '\\\\Symfony", "DEFAULT_NAMESPACE = '\\\\".$prefix."\\\\Symfony", $contents);
            // $contents = str_replace("AnnotationReader::class", $prefix."\\AnnotationReader::class", $contents);
            // $contents = str_replace("use ".$prefix."\\Composer\\InstalledVersions", "use Composer\\InstalledVersions", $contents);

            $contents = preg_replace(
                '/(.*->load\((?:\n\s+)?\')(.+?\\\\)(\',.*)/',
                '$1'.$prefix.'\\\\$2$3',
                $contents,
            );

            //createClassInstance

            // Change the contents here.
            // print "working on $filePath\n";
            // if (strpos($filePath,'zugferd/src/')) {
            //     return preg_replace(
            //         "%rsm%",
            //         "horstoeko\\\\zugferd\\\\entities\\\\basic\\\\rsm",
            //         $contents
            //     );
            // }
            return $contents;
        },
    ],

    // List of symbols to consider internal i.e. to leave untouched.
    //
    // For more information see: https://github.com/humbug/php-scoper/blob/master/docs/configuration.md#excluded-symbols
    'exclude-namespaces' => [
        // 'Acme\Foo'                     // The Acme\Foo namespace (and sub-namespaces)
        // '~^PHPUnit\\\\Framework$~',    // The whole namespace PHPUnit\Framework (but not sub-namespaces)
        '~^$~',                        // The root namespace only
        //'',                            // Any namespace
        'custom\facturx',
        // 'ram',
        // 'rsm',
        // 'udt',
        // 'qdt',
    ],
    'exclude-classes' => [
        // 'ReflectionClassConstant',
        'Stringeable',
        '/regex/',
        'ActionsFacturX',
        // 'ram',
        // 'rsm',
        // 'udt',
        // 'qdt',
        'PHPUnit',
		'ZugferdPackageVersion'
	],
    'exclude-functions' => [
        '/createClassInstance/',
        '/setExchangedDocumentContext/',
        'mb_str_split',
        'str_contains',
    ],
    'exclude-constants' => [
        // 'STDIN',
        'PHP_EOL', '/regex/'
    ],

    // List of symbols to expose.
    //
    // For more information see: https://github.com/humbug/php-scoper/blob/master/docs/configuration.md#exposed-symbols
    'expose-global-constants' => true,
    'expose-global-classes' => true,
    'expose-global-functions' => true,
    'expose-namespaces' => [
        // 'Acme\Foo'                     // The Acme\Foo namespace (and sub-namespaces)
        // '~^PHPUnit\\\\Framework$~',    // The whole namespace PHPUnit\Framework (but not sub-namespaces)
        // '~^$~',                        // The root namespace only
        // '',                            // Any namespace
    ],
    'expose-classes' => [],
    'expose-functions' => [],
    'expose-constants' => [],
];
