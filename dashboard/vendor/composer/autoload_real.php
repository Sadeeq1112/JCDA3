<?php

// autoload_real.php @generated by Composer

class ComposerAutoloaderInit2fd6dfa625e3778d4b221a2570d125e6
{
    private static $loader;

    public static function loadClassLoader($class)
    {
        if ('Composer\Autoload\ClassLoader' === $class) {
            require __DIR__ . '/ClassLoader.php';
        }
    }

    /**
     * @return \Composer\Autoload\ClassLoader
     */
    public static function getLoader()
    {
        if (null !== self::$loader) {
            return self::$loader;
        }

        require __DIR__ . '/platform_check.php';

        spl_autoload_register(array('ComposerAutoloaderInit2fd6dfa625e3778d4b221a2570d125e6', 'loadClassLoader'), true, true);
        self::$loader = $loader = new \Composer\Autoload\ClassLoader(\dirname(__DIR__));
        spl_autoload_unregister(array('ComposerAutoloaderInit2fd6dfa625e3778d4b221a2570d125e6', 'loadClassLoader'));

        require __DIR__ . '/autoload_static.php';
        call_user_func(\Composer\Autoload\ComposerStaticInit2fd6dfa625e3778d4b221a2570d125e6::getInitializer($loader));

        $loader->register(true);

        return $loader;
    }
}
