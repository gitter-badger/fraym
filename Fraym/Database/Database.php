<?php
/**
 * @link      http://fraym.org
 * @author    Dominik Weber <info@fraym.org>
 * @copyright Dominik Weber <info@fraym.org>
 * @license   http://www.opensource.org/licenses/gpl-license.php GNU General Public License, version 2 or later (see the LICENSE file)
 */
namespace Fraym\Database;

/**
 * Class Database
 * @package Fraym\Database
 * @Injectable(lazy=true)
 */
class Database
{
    /**
     * @var \Doctrine\ORM\EntityManager|null
     */
    public $entityManager = null;

    /**
     * @var \Doctrine\Common\EventManager|null
     */
    public $eventManager = null;

    /**
     * @var null
     */
    private $fetchMode = null;

    /**
     * @var \Doctrine\DBAL\Connection|null
     */
    private $pdo = null;

    /**
     * @var null
     */
    private $schemaTool = null;

    /**
     * @var bool|string
     */
    private $moduleDirCacheFile = false;

    /**
     * @var bool|\Doctrine\Common\Annotations\CachedReader
     */
    private $cachedAnnotationReader = false;

    /**
     * @var array
     */
    private $connectionOptions = array();

    /**
     * @Inject
     * @var \Fraym\FileManager\FileManager
     */
    protected $fileManager;

    /**
     * @Inject
     * @var \Fraym\Locale\Locale
     */
    protected $locale;

    /**
     * @Inject
     * @var \Fraym\Core
     */
    protected $core;

    /**
     * @Inject
     * @var \Fraym\Entity\EventListener
     */
    protected $eventListener;

    /**
     * Call default doctrine entity manager methods
     *
     * @param $method
     * @param $param
     * @return bool|mixed
     */
    public function __call($method, $param)
    {
        if (is_object($this->entityManager) && method_exists($this->entityManager, $method)) {
            return call_user_func_array(array(&$this->entityManager, $method), $param);
        }
        return null;
    }

    /**
     * @return array
     */
    public function getModelDirs()
    {
        $entities = $this->fileManager->findFiles(
            $this->core->getApplicationDir() . DIRECTORY_SEPARATOR . 'Entity',
            GLOB_ONLYDIR
        );

        return $entities;
    }

    /**
     * @param bool $deleteCacheFile
     * @return $this
     */
    public function createModuleDirCache($deleteCacheFile = false)
    {
        if ($deleteCacheFile && is_file($this->moduleDirCacheFile)) {
            unlink($this->moduleDirCacheFile);
        }
        if (!is_file($this->moduleDirCacheFile)) {
            $modelDirs = $this->getModelDirs();
            file_put_contents($this->moduleDirCacheFile, json_encode($modelDirs));
        }
        return $this;
    }

    /**
     * @return array|mixed
     */
    public function getModuleDirCache()
    {
        if (is_file($this->moduleDirCacheFile)) {
            return json_decode(file_get_contents($this->moduleDirCacheFile));
        }
        return array();
    }

    /**
     * @return bool|string
     */
    public function getModuleDirCacheFile()
    {
        if ($this->moduleDirCacheFile === false) {
            $applicationDir = $this->core->getApplicationDir();

            $this->moduleDirCacheFile = $applicationDir . DIRECTORY_SEPARATOR .
                'Cache' . DIRECTORY_SEPARATOR .
                'doctrine_module_dir.cache';
        }
        return $this->moduleDirCacheFile;
    }

    /**
     * @return $this
     */
    public function connect()
    {
        // Prevent to connect twice
        if ($this->entityManager) {
            return $this;
        }

        $applicationDir = $this->core->getApplicationDir();

        $this->moduleDirCacheFile = $applicationDir . DIRECTORY_SEPARATOR .
            'Cache' . DIRECTORY_SEPARATOR .
            'doctrine_module_dir.cache';

        $this->createModuleDirCache();

        if (APC_ENABLED && ENV !== \Fraym\Core::ENV_DEVELOPMENT) {
            $cache = new \Doctrine\Common\Cache\ApcCache();
        } else {
            $cache = new \Doctrine\Common\Cache\ArrayCache;
        }

        $cache->setNamespace('Fraym_instance_' . FRAYM_INSTANCE);

        $config = new \Doctrine\ORM\Configuration;
        $config->setMetadataCacheImpl($cache);

        $config->addCustomStringFunction('MD5', '\DoctrineExtensions\Query\Mysql\Md5');
        $config->addCustomStringFunction('ACOS', '\DoctrineExtensions\Query\Mysql\Acos');
        $config->addCustomStringFunction('ASIN', '\DoctrineExtensions\Query\Mysql\Asin');
        $config->addCustomStringFunction('ATAN', '\DoctrineExtensions\Query\Mysql\Atan');
        $config->addCustomStringFunction('ATAN2', '\DoctrineExtensions\Query\Mysql\Atan2');
        $config->addCustomStringFunction('BINARY', '\DoctrineExtensions\Query\Mysql\Binary');
        $config->addCustomStringFunction('CHARLENGTH', '\DoctrineExtensions\Query\Mysql\CharLength');
        $config->addCustomStringFunction('CONCATWS', '\DoctrineExtensions\Query\Mysql\ConcatWs');
        $config->addCustomStringFunction('COS', '\DoctrineExtensions\Query\Mysql\Cos');
        $config->addCustomStringFunction('COT', '\DoctrineExtensions\Query\Mysql\COT');
        $config->addCustomStringFunction('COUNTIF', '\DoctrineExtensions\Query\Mysql\CountIf');
        $config->addCustomStringFunction('CRC32', '\DoctrineExtensions\Query\Mysql\Crc32');
        $config->addCustomStringFunction('DATE', '\DoctrineExtensions\Query\Mysql\Date');
        $config->addCustomStringFunction('DATEADD', '\DoctrineExtensions\Query\Mysql\DateAdd');
        $config->addCustomStringFunction('DATEDIFF', '\DoctrineExtensions\Query\Mysql\DateFormat');
        $config->addCustomStringFunction('DAY', '\DoctrineExtensions\Query\Mysql\Day');
        $config->addCustomStringFunction('DEGREES', '\DoctrineExtensions\Query\Mysql\Degrees');
        $config->addCustomStringFunction('FIELD', '\DoctrineExtensions\Query\Mysql\Field');
        $config->addCustomStringFunction('FINDINSET', '\DoctrineExtensions\Query\Mysql\FindInSet');
        $config->addCustomStringFunction('GROUPCONCAT', '\DoctrineExtensions\Query\Mysql\GroupConcat');
        $config->addCustomStringFunction('HOUR', '\DoctrineExtensions\Query\Mysql\Hour');
        $config->addCustomStringFunction('IFELSE', '\DoctrineExtensions\Query\Mysql\IfElse');
        $config->addCustomStringFunction('IFNULL', '\DoctrineExtensions\Query\Mysql\IfNUll');
        $config->addCustomStringFunction('MATCHAGAINST', '\DoctrineExtensions\Query\Mysql\MatchAgainst');
        $config->addCustomStringFunction('MONTH', '\DoctrineExtensions\Query\Mysql\Month');
        $config->addCustomStringFunction('NULLIF', '\DoctrineExtensions\Query\Mysql\NullIf');
        $config->addCustomStringFunction('PI', '\DoctrineExtensions\Query\Mysql\Pi');
        $config->addCustomStringFunction('RADIANS', '\DoctrineExtensions\Query\Mysql\Radians');
        $config->addCustomStringFunction('RAND', '\DoctrineExtensions\Query\Mysql\Rand');
        $config->addCustomStringFunction('REGEXP', '\DoctrineExtensions\Query\Mysql\Regexp');
        $config->addCustomStringFunction('ROUND', '\DoctrineExtensions\Query\Mysql\Round');
        $config->addCustomStringFunction('SHA1', '\DoctrineExtensions\Query\Mysql\Sha1');
        $config->addCustomStringFunction('SHA2', '\DoctrineExtensions\Query\Mysql\Sha2');
        $config->addCustomStringFunction('SIN', '\DoctrineExtensions\Query\Mysql\Sin');
        $config->addCustomStringFunction('STRTODATE', '\DoctrineExtensions\Query\Mysql\StrToDate');
        $config->addCustomStringFunction('TAN', '\DoctrineExtensions\Query\Mysql\Tan');
        $config->addCustomStringFunction('TIMESTAMPDIFF', '\DoctrineExtensions\Query\Mysql\TimestampDiff');
        $config->addCustomStringFunction('WEEK', '\DoctrineExtensions\Query\Mysql\Week');
        $config->addCustomStringFunction('YEAR', '\DoctrineExtensions\Query\Mysql\Year');

        if (!defined('ENV') || ENV === \Fraym\Core::ENV_DEVELOPMENT) {
            $config->setAutoGenerateProxyClasses(true);
        }
        $modelDirs = $this->getModuleDirCache();

        \Doctrine\Common\Annotations\AnnotationRegistry::registerLoader(
            function ($class) {
                return class_exists($class, true);
            }
        );

        $annotationReader = new \Doctrine\Common\Annotations\AnnotationReader();
        $this->cachedAnnotationReader = new \Doctrine\Common\Annotations\CachedReader($annotationReader, $cache);
        $annotationDriver = new \Doctrine\ORM\Mapping\Driver\AnnotationDriver($annotationReader, $modelDirs);

        /**
         * Ignore PHP-DI Annotation
         */
        $annotationReader->addGlobalIgnoredName('Injectable');
        $annotationReader->addGlobalIgnoredName('Inject');

        $config->setMetadataDriverImpl($annotationDriver);
        $config->setQueryCacheImpl($cache);
        $config->setResultCacheImpl($cache);
        $config->setProxyDir($applicationDir . DIRECTORY_SEPARATOR . 'Cache/DoctrineProxies');
        $config->setProxyNamespace('Proxies');
        $this->fetchMode = \PDO::FETCH_OBJ;

        $tablePrefix = new \DoctrineExtensions\TablePrefix(DB_TABLE_PREFIX);
        $this->eventManager = new \Doctrine\Common\EventManager();

        $this->eventManager->addEventListener(\Doctrine\ORM\Events::loadClassMetadata, $tablePrefix);
        $this->entityManager = \Doctrine\ORM\EntityManager::create(
            $this->connectionOptions,
            $config,
            $this->eventManager
        );

        $this->pdo = $this->entityManager->getConnection();
        $this->pdo->getDatabasePlatform()->registerDoctrineTypeMapping('set', 'string');

        $driverChain = new \Doctrine\ORM\Mapping\Driver\DriverChain();
        \Gedmo\DoctrineExtensions::registerAbstractMappingIntoDriverChainORM(
            $driverChain, // our metadata driver chain, to hook into
            $this->cachedAnnotationReader // our cached annotation reader
        );

        $this->eventManager->addEventListener(
            array(
                \Doctrine\ORM\Events::preRemove,
                \Doctrine\ORM\Events::postRemove,
                \Doctrine\ORM\Events::prePersist,
                \Doctrine\ORM\Events::postPersist,
                \Doctrine\ORM\Events::preUpdate,
                \Doctrine\ORM\Events::postUpdate,
                \Doctrine\ORM\Events::postLoad,
                \Doctrine\ORM\Events::onFlush,
            ),
            $this->eventListener
        );

        return $this;
    }

    /**
     * @Inject({"connectionOptions" = "db.options"})
     * @param array $connectionOptions
     */
    public function __construct($connectionOptions = array())
    {

        if (count($connectionOptions) == 0) {
            $connectionOptions = array(
                'driver' => 'pdo_mysql',
                'user' => DB_USER,
                'password' => DB_PASS,
                'host' => DB_HOST,
                'dbname' => DB_NAME,
                'charset' => 'UTF8',
            );
        }

        $this->connectionOptions = $connectionOptions;
        return $this;
    }

    /**
     * @return \Doctrine\ORM\Tools\SchemaTool|null
     */
    public function getSchemaTool()
    {
        if ($this->schemaTool === null) {
            $this->schemaTool = new \Doctrine\ORM\Tools\SchemaTool($this->entityManager);
        }
        return $this->schemaTool;
    }

    /**
     * @return $this
     * @throws \Exception
     */
    public function setUpTranslateable()
    {
        $defaultLocale = $this->locale->getDefaultLocale();
        if ($defaultLocale === null) {
            throw new \Exception('Default locale not found! Fraym is not correctly installed, please reinstall Fraym.');
        }
        $translatableListener = new \Gedmo\Translatable\TranslatableListener;

        $translatableListener->setDefaultLocale($defaultLocale->locale);
        $translatableListener->setAnnotationReader($this->cachedAnnotationReader);
        $translatableListener->setTranslationFallback(true);
        $translatableListener->setPersistDefaultLocaleTranslation(true);
        $this->eventManager->addEventSubscriber($translatableListener);
        return $this;
    }

    /**
     * @return $this
     */
    public function setUpSortable()
    {
        $this->eventManager->addEventSubscriber(new \Gedmo\Sortable\SortableListener);
        return $this;
    }

    /**
     * @return bool|\Doctrine\Common\Annotations\CachedReader
     */
    public function getAnnotationReader()
    {
        return $this->cachedAnnotationReader;
    }

    /**
     * @return \Doctrine\ORM\EntityManager|null
     */
    public function getEntityManager()
    {
        return $this->entityManager;
    }

    /**
     * @param $dqlQuery
     * @param array $params
     * @return mixed
     */
    public function queryDql($dqlQuery, $params = array())
    {
        $query = $this->entityManager->createQuery($dqlQuery);
        if (count($params) > 0) {
            $query->setParameters($params);
        }
        return $query->getResult();
    }

    /**
     * returns all founded rows
     *
     * @param string $query
     * @param array $var
     *
     * @return object
     */
    public function query($query, $var = array())
    {
        $sth = $this->exec($query, $var);
        $result = $sth->fetchAll($this->fetchMode);

        return $result;
    }

    /**
     * @param $query
     * @param array $var
     * @return bool
     */
    public function exec($query, $var = array())
    {
        $sth = $this->pdo->prepare($query);
        $result = $sth->execute($var);

        return $result;
    }

    /**
     * returns a founded col
     *
     * @param string $query
     * @param array $var
     * @param int $col the col number (default 0 = the first column)
     *
     * @return array
     */
    public function queryCol($query, $var = array(), $col = 0)
    {
        $sth = $this->exec($query, $var);
        $result = $sth->fetchColumn($col);

        return $result;
    }

    /**
     * returns one founded field
     *
     * @param string $query
     * @param array $var
     *
     * @return string|null
     */
    public function queryOne($query, $var = array())
    {
        $sth = $this->exec($query, $var);
        $result = $sth->fetchAll();
        $result = (isset($result[0][0]) ? $result[0][0] : null);

        return $result;
    }

    /**
     * returns a row
     *
     * @param string $query
     * @param array $var
     *
     * @return object
     */
    public function queryRow($query, $var = array())
    {
        $sth = $this->exec($query, $var);
        $result = $sth->fetchAll($this->fetchMode);

        $result = (isset($result[0]) ? $result[0] : false);
        return $result;
    }

    /**
     * gets information about a column
     *
     * @param string $query
     * @param array $var
     * @param int $col
     *
     * @return object
     */
    public function getColInfo($query, $var = array(), $col = 0)
    {
        $sth = $this->exec($query, $var);
        return $sth->getColumnMeta($col);
    }


    /**
     * Validates the metadata mapping for Doctrine, using the SchemaValidator
     * of Doctrine.
     *
     * @return array
     */
    public function validateMapping()
    {
        try {
            $validator = new \Doctrine\ORM\Tools\SchemaValidator($this->entityManager);
            return $validator->validateMapping();
        } catch (\Exception $exception) {
            return array(array($exception->getMessage()));
        }
    }

    /**
     * Creates the needed DB schema using Doctrine's SchemaTool. If tables already
     * exist, this will thow an exception.
     *
     * @param string $outputPathAndFilename A file to write SQL to, instead of executing it
     * @return string
     */
    public function createSchema($outputPathAndFilename = null)
    {
        $this->createModuleDirCache(true);
        if ($outputPathAndFilename === null) {
            $this->getSchemaTool()->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());
        } else {
            file_put_contents(
                $outputPathAndFilename,
                implode(
                    PHP_EOL,
                    $this->getSchemaTool()->getCreateSchemaSql(
                        $this->entityManager->getMetadataFactory()->getAllMetadata()
                    )
                )
            );
        }
    }

    /**
     * Updates the DB schema using Doctrine's SchemaTool. The $safeMode flag is passed
     * to SchemaTool unchanged.
     *
     * @param boolean $safeMode
     * @param string $outputPathAndFilename A file to write SQL to, instead of executing it
     * @return string
     */
    public function updateSchema($safeMode = true, $outputPathAndFilename = null)
    {
        $this->createModuleDirCache(true);
        if ($outputPathAndFilename === null) {
            return $this->getSchemaTool()->updateSchema(
                $this->entityManager->getMetadataFactory()->getAllMetadata(),
                $safeMode
            );
        } else {
            return file_put_contents(
                $outputPathAndFilename,
                implode(
                    PHP_EOL,
                    $this->getSchemaTool()->getUpdateSchemaSql(
                        $this->entityManager->getMetadataFactory()->getAllMetadata(),
                        $safeMode
                    )
                )
            );
        }
    }

    /**
     * Compiles the Doctrine proxy class code using the Doctrine ProxyFactory.
     *
     * @return void
     */
    public function compileProxies()
    {
        $proxyFactory = $this->entityManager->getProxyFactory();
        $proxyFactory->generateProxyClasses($this->entityManager->getMetadataFactory()->getAllMetadata());
    }

    /**
     * Returns information about which entities exist and possibly if their
     * mapping information contains errors or not.
     *
     * @return array
     */
    public function getEntityStatus()
    {
        $entityClassNames = $this->entityManager->getConfiguration()->getMetadataDriverImpl()->getAllClassNames();
        $info = array();
        foreach ($entityClassNames as $entityClassName) {
            try {
                $this->entityManager->getClassMetadata($entityClassName);
                $info[$entityClassName] = true;
            } catch (\Doctrine\ORM\Mapping\MappingException $e) {
                $info[$entityClassName] = $e->getMessage();
            }
        }

        return $info;
    }
}
