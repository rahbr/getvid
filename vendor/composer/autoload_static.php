<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit56f6ab8bfd8d6631d15d64f7f92cefe9
{
    public static $prefixesPsr0 = array (
        'Y' => 
        array (
            'Youtube' => 
            array (
                0 => 'C:\\gdrive\\localhost\\getvid\\vendor',
            ),
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixesPsr0 = ComposerStaticInit56f6ab8bfd8d6631d15d64f7f92cefe9::$prefixesPsr0;

        }, null, ClassLoader::class);
    }
}