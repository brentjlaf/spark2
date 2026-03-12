<?php
// Lightweight environment file loader for DB credentials

/**
 * Load environment variables from CMS/data/.env.php if present.
 *
 * The file should return an associative array of key => value pairs.
 */
function load_env_from_file(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }

    $envPath = __DIR__ . '/../data/.env.php';
    if (is_file($envPath)) {
        $values = include $envPath;
        if (is_array($values)) {
            foreach ($values as $key => $value) {
                if (!is_string($key) || $value === null) {
                    continue;
                }
                if (getenv($key) === false) {
                    putenv($key . '=' . $value);
                }
            }
        }
    }

    $loaded = true;
}
?>
