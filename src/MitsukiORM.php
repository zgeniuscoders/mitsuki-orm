<?php

declare(strict_types=1);

namespace Mitsuki\ORM;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Mitsuki\ORM\Mapping\MitsukiRuntimeDriver;

/**
 * MitsukiORM
 *
 * The main entry point for the Tsuki ORM framework. This factory class simplifies 
 * the instantiation and configuration of the Doctrine EntityManager, integrating 
 * the custom Mitsuki runtime mapping driver and default naming strategies.
 *
 * 
 *
 * @package Mitsuki\ORM
 * @author Zgenius matondo <zgeniuscoders@gmail.com>
 */
class MitsukiORM
{
    /**
     * Creates and configures a Doctrine EntityManager tailored for Mitsuki ORM.
     *
     * This method initializes the ORM configuration, attaches the custom 
     * MitsukiRuntimeDriver for attribute-based mapping, and sets up 
     * proxy generation for lazy loading.
     *
     * @param array<string, mixed> $dbParams Connection parameters (e.g., driver, host, user, password).
     * @param string $cachePath Path to the directory where generated proxies and cache files will be stored.
     * @param bool $isDevMode Whether the application is in development mode. 
     * If true, proxy classes are regenerated automatically.
     *
     * @return EntityManager The configured Doctrine EntityManager instance.
     * @throws \Doctrine\DBAL\Exception If the database connection cannot be established.
     * @throws \RuntimeException If the proxy directory cannot be created.
     */
    public static function create(
        array $dbParams,
        string $cachePath,
        bool $isDevMode = true
    ): EntityManager {
        
        $config = ORMSetup::createConfiguration($isDevMode);

        $config->setMetadataDriverImpl(new MitsukiRuntimeDriver());

        // Enforce snake_case naming convention for database tables and columns
        $config->setNamingStrategy(new UnderscoreNamingStrategy());

        // Configure Proxy settings for Lazy Loading
        $proxyDir = $cachePath . '/orm/proxies';
        if (!is_dir($proxyDir)) {
            if (!mkdir($proxyDir, 0777, true) && !is_dir($proxyDir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $proxyDir));
            }
        }

        $config->setProxyDir($proxyDir);
        $config->setProxyNamespace('Mitsuki\ORM\Proxies');
        $config->setAutoGenerateProxyClasses($isDevMode);

        $connection = DriverManager::getConnection($dbParams, $config);

        return new EntityManager($connection, $config);
    }
}