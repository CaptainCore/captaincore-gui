<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit9f7509cc1c55bc410ccf6f05510f2050
{
    public static $prefixLengthsPsr4 = array (
        'C' => 
        array (
            'CaptainCore\\' => 12,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'CaptainCore\\' => 
        array (
            0 => __DIR__ . '/../..' . '/app',
        ),
    );

    public static $classMap = array (
        'CaptainCore\\Account' => __DIR__ . '/../..' . '/app/Account.php',
        'CaptainCore\\Accounts' => __DIR__ . '/../..' . '/app/Accounts.php',
        'CaptainCore\\DB' => __DIR__ . '/../..' . '/app/DB.php',
        'CaptainCore\\Domains' => __DIR__ . '/../..' . '/app/Domains.php',
        'CaptainCore\\Environments' => __DIR__ . '/../..' . '/app/Environments.php',
        'CaptainCore\\Invite' => __DIR__ . '/../..' . '/app/Invite.php',
        'CaptainCore\\Invites' => __DIR__ . '/../..' . '/app/Invites.php',
        'CaptainCore\\Keys' => __DIR__ . '/../..' . '/app/Keys.php',
        'CaptainCore\\Quicksaves' => __DIR__ . '/../..' . '/app/Quicksaves.php',
        'CaptainCore\\Recipes' => __DIR__ . '/../..' . '/app/Recipes.php',
        'CaptainCore\\Site' => __DIR__ . '/../..' . '/app/Site.php',
        'CaptainCore\\Sites' => __DIR__ . '/../..' . '/app/Sites.php',
        'CaptainCore\\Snapshots' => __DIR__ . '/../..' . '/app/Snapshots.php',
        'CaptainCore\\UpdateLogs' => __DIR__ . '/../..' . '/app/UpdateLogs.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit9f7509cc1c55bc410ccf6f05510f2050::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit9f7509cc1c55bc410ccf6f05510f2050::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit9f7509cc1c55bc410ccf6f05510f2050::$classMap;

        }, null, ClassLoader::class);
    }
}
