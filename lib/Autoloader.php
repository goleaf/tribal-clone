<?php
declare(strict_types=1);

/**
 * Autoloader class - loads classes automatically
 */
class Autoloader 
{
    /**
     * Registers the autoloader
     */
    public static function register(): void 
    {
        spl_autoload_register([self::class, 'loadClass']);
    }
    
    /**
     * Loads a class by its name
     *
     * @param string $className Class name to load
     * @return void
     */
    public static function loadClass(string $className): void 
    {
        // Check whether the class name contains a namespace
        if (strpos($className, '\\') !== false) {
            // Replace backslash with directory separator
            $className = str_replace('\\', DIRECTORY_SEPARATOR, $className);
        }
        
        // Base paths where classes may live
        $paths = [
            'lib/',
            'lib/managers/',
            'lib/hooks/',
            'lib/models/',
            'lib/utils/',
            'app/core/',
            'app/controllers/',
        ];
        
        // Check each path
        foreach ($paths as $path) {
            $file = $path . $className . '.php';
            
            // Load the file if it exists
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
}

// Register the autoloader
Autoloader::register(); 
