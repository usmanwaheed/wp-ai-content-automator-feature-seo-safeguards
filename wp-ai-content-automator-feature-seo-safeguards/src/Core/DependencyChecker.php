<?php
namespace AICA\Core;

/**
 * Centralized dependency validation system
 */
class DependencyChecker
{
    /**
     * Check if all required dependencies are available
     */
    public static function check(): bool
    {
        $missing = self::getMissingDependencies();
        
        if (!empty($missing)) {
            add_action('admin_notices', [__CLASS__, 'showMissingDependenciesNotice']);
            return false;
        }
        
        return true;
    }
    
    /**
     * Get list of missing dependencies
     */
    public static function getMissingDependencies(): array
    {
        $missing = [];
        
        if (!class_exists('\Opis\JsonSchema\Validator')) {
            $missing[] = 'opis/json-schema';
        }
        
        return $missing;
    }
    
    /**
     * Show admin notice for missing dependencies
     */
    public static function showMissingDependenciesNotice(): void
    {
        $missing = self::getMissingDependencies();
        
        if (empty($missing)) {
            return;
        }
        
        $packages = implode(', ', array_map(function($pkg) {
            return "<code>{$pkg}</code>";
        }, $missing));
        
        $pluginDir = dirname(dirname(__DIR__));
        
        echo '<div class="error"><p>';
        echo '<strong>AI Content Automator Error:</strong> ';
        echo 'Required dependencies are missing: ' . $packages . '. ';
        echo 'Please run <code>composer install</code> from the plugin directory: ';
        echo '<code>' . esc_html($pluginDir) . '</code>';
        echo '</p></div>';
    }
    
    /**
     * Check if specific dependency is available
     */
    public static function hasDependency(string $dependency): bool
    {
        switch ($dependency) {
            case 'opis/json-schema':
                return class_exists('\Opis\JsonSchema\Validator');
            default:
                return false;
        }
    }
}
