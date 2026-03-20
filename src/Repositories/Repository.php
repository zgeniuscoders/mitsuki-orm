<?php

declare(strict_types=1);

namespace Mitsuki\ORM\Repositories;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * Base Repository providing common CRUD operations, advanced querying, 
 * and relationship management with metadata caching.
 * * @author Zgenius matondo <zgeniuscoders@gmail.com>
 */
abstract class Repository
{
    /** @var string The fully qualified class name of the managed entity */
    protected string $entityClass;

    /** @var array<string, string> In-memory cache for repository-to-entity mapping */
    private static array $metadataCache = [];

    /** @var Filesystem Symfony Filesystem component for cache management */
    private Filesystem $filesystem;

    /**
     * @param EntityManager $em        The Doctrine EntityManager
     * @param string        $cachePath The filesystem path for storing metadata cache
     * @param bool          $useCache  Whether to use and generate persistent file cache
     * * @throws \RuntimeException If the entity class cannot be discovered via reflection
     */
    public function __construct(
        protected EntityManager $em,
        private string $cachePath,
        private bool $useCache = false
    ) {
        $this->filesystem = new Filesystem();
        $this->entityClass = $this->resolveEntityClass();
    }

    /**
     * Resolves the entity class name using memory, file cache, or reflection.
     * * @return string
     */
    private function resolveEntityClass(): string
    {
        $repositoryClass = static::class;

        // 1. Check in-memory static cache
        if (isset(self::$metadataCache[$repositoryClass])) {
            return self::$metadataCache[$repositoryClass];
        }

        $cacheFile = Path::join($this->cachePath, 'mitsuki_repo_map.php');

        // 2. Load from physical cache file if enabled
        if ($this->useCache && $this->filesystem->exists($cacheFile)) {
            $map = include $cacheFile;
            if (isset($map[$repositoryClass])) {
                return self::$metadataCache[$repositoryClass] = $map[$repositoryClass];
            }
        }

        // 3. Fallback to Reflection
        $entityClass = $this->discoverEntityViaReflection();

        // 4. Update physical cache if enabled
        if ($this->useCache) {
            $this->warmupCache($cacheFile, $repositoryClass, $entityClass);
        }

        return self::$metadataCache[$repositoryClass] = $entityClass;
    }

    /**
     * Discovers the entity class by analyzing protected properties ending with 'Entity'.
     * * @return string
     * @throws \RuntimeException
     */
    private function discoverEntityViaReflection(): string
    {
        $reflection = new ReflectionClass($this);
        foreach ($reflection->getProperties(ReflectionProperty::IS_PROTECTED) as $property) {
            if (str_ends_with($property->getName(), 'Entity')) {
                $type = $property->getType();
                if ($type && !$type->isBuiltin()) {
                    return $type->getName();
                }
            }
        }
        throw new \RuntimeException("No protected *Entity property found in " . static::class);
    }

    /**
     * Persists the repository mapping to a PHP file for production performance.
     * * @param string $cacheFile
     * @param string $repoClass
     * @param string $entityClass
     */
    private function warmupCache(string $cacheFile, string $repoClass, string $entityClass): void
    {
        $currentMap = $this->filesystem->exists($cacheFile) ? include $cacheFile : [];
        $currentMap[$repoClass] = $entityClass;

        $content = "<?php\n\nreturn " . var_export($currentMap, true) . ";";
        $this->filesystem->dumpFile($cacheFile, $content);
    }

    /**
     * Persists an entity to the database.
     * * @param object $entity
     */
    public function save(object $entity): void
    {
        $this->em->persist($entity);
        $this->em->flush();
    }

    /**
     * Removes an entity from the database.
     * * @param object $entity
     */
    public function delete(object $entity): void
    {
        $this->em->remove($entity);
        $this->em->flush();
    }

    /**
     * Finds an entity by its primary identifier.
     * * @param int|string $id
     * @return object|null
     */
    public function find(int|string $id): ?object
    {
        return $this->em->find($this->entityClass, $id);
    }

    /**
     * Retrieves all entities of the managed class.
     * * @return array
     */
    public function findAll(): array
    {
        return $this->em->getRepository($this->entityClass)->findAll();
    }

    /**
     * Counts entities matching the given criteria.
     * * @param array $criteria
     * @return int
     */
    public function count(array $criteria = []): int
    {
        return $this->em->getRepository($this->entityClass)->count($criteria);
    }

    /**
     * Finds entities by a set of criteria.
     * * @param array      $criteria
     * @param array|null $orderBy
     * @param int|null   $limit
     * @param int|null   $offset
     * @return array
     */
    public function where(array $criteria, ?array $orderBy = null, $limit = null, $offset = null): array
    {
        return $this->em->getRepository($this->entityClass)->findBy($criteria, $orderBy, $limit, $offset);
    }

    /**
     * Creates a QueryBuilder with multiple AND conditions.
     * * @param array $criteria Key-value pairs for filtering
     * @return QueryBuilder
     */
    public function whereAnd(array $criteria): QueryBuilder
    {
        $qb = $this->createQueryBuilder('e');
        foreach ($criteria as $field => $value) {
            $qb->andWhere("e.$field = :$field")
                ->setParameter($field, $value);
        }
        return $qb;
    }

    /**
     * Creates a QueryBuilder with multiple OR conditions.
     * * @param array $criteria Key-value pairs for filtering
     * @return QueryBuilder
     */
    public function whereOr(array $criteria): QueryBuilder
    {
        $qb = $this->createQueryBuilder('e');
        foreach ($criteria as $field => $value) {
            $qb->orWhere("e.$field = :$field")
                ->setParameter($field, $value);
        }
        return $qb;
    }

    /**
     * Returns results sorted by a specific field.
     * * @param string $field
     * @param string $direction 'ASC' or 'DESC'
     * @return array
     */
    public function sort(string $field, string $direction = 'ASC'): array
    {
        return $this->where([], [$field => $direction]);
    }

    /**
     * Paginates results using Doctrine Paginator.
     * * @param int $page  Current page number (starts at 1)
     * @param int $limit Number of results per page
     * @return Paginator
     */
    public function paginate(int $page = 1, int $limit = 10): Paginator
    {
        $query = $this->createQueryBuilder('e')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery();

        return new Paginator($query);
    }

    /**
     * Creates a new QueryBuilder instance for the managed entity.
     * * @param string $alias
     * @return QueryBuilder
     */
    public function createQueryBuilder(string $alias): QueryBuilder
    {
        return $this->em->createQueryBuilder()
            ->select($alias)
            ->from($this->entityClass, $alias);
    }

    /**
     * Retrieves a related collection (OneToMany or ManyToMany).
     * * @param object $entity   The owner entity
     * @param string $property The name of the relation property
     * @return iterable
     * @throws \InvalidArgumentException If property is not a valid association
     */
    public function getCollection(object $entity, string $property): iterable
    {
        $metadata = $this->em->getClassMetadata($this->entityClass);

        if (!$metadata->hasAssociation($property)) {
            throw new \InvalidArgumentException("Property '$property' is not a valid association.");
        }

        $getter = 'get' . ucfirst($property);
        if (method_exists($entity, $getter)) {
            return $entity->$getter();
        }

        return $entity->$property;
    }

    /**
     * Adds a related entity to a collection and saves the owner.
     * * @param object $entity        The owner entity
     * @param string $property      The relation property name
     * @param object $relatedEntity The entity to add
     * @throws \LogicException If the property does not support adding elements
     */
    public function addRelated(object $entity, string $property, object $relatedEntity): void
    {
        $collection = $this->getCollection($entity, $property);

        if ($collection instanceof \Doctrine\Common\Collections\Collection || method_exists($collection, 'add')) {
            $collection->add($relatedEntity);
        } elseif (is_array($collection)) {
            $collection[] = $relatedEntity;
        } else {
            throw new \LogicException("The property '$property' does not support adding elements.");
        }

        $this->save($entity);
    }

    /**
     * Retrieves a single related entity (ManyToOne or OneToOne).
     * * @param object $entity
     * @param string $property
     * @return object|null
     */
    public function getRelated(object $entity, string $property): ?object
    {
        return $this->getCollection($entity, $property);
    }

    /**
     * Clears the static in-memory cache. Useful for test isolation.
     */
    public static function clearInternalCache(): void
    {
        self::$metadataCache = [];
    }
}