<?php

declare(strict_types=1);

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\Filesystem\Filesystem;
use Tests\Fixtures\MockEntity;
use Tests\Fixtures\MockRepository;

/**
 * Unit tests for the Base Repository class.
 * Covers infrastructure (caching/reflection), CRUD, advanced queries, and relationships.
 */

beforeEach(function () {
    // Reset static in-memory cache to ensure test isolation
    MockRepository::clearInternalCache();

    $this->cachePath = __DIR__ . '/../../var/cache/test_repos';
    $this->fs = new Filesystem();

    // Clean up and recreate the cache directory for every test
    if ($this->fs->exists($this->cachePath)) {
        $this->fs->remove($this->cachePath);
    }
    $this->fs->mkdir($this->cachePath);

    // Mock Doctrine dependencies
    $this->em = mock(EntityManager::class);
    $this->doctrineRepo = mock(EntityRepository::class);
    $this->metadata = mock(ClassMetadata::class);

    // Default metadata expectation
    $this->em->shouldReceive('getClassMetadata')->andReturn($this->metadata);
});

afterEach(function () {
    $this->fs->remove($this->cachePath);
});

/**
 * INFRASTRUCTURE TESTS (CACHE & REFLECTION)
 */

test('it does NOT create a cache file if useCache is false', function () {
    new MockRepository($this->em, $this->cachePath, false);
    $cacheFile = $this->cachePath . '/mitsuki_repo_map.php';

    expect($this->fs->exists($cacheFile))->toBeFalse();
});

test('it creates a cache file if useCache is true', function () {
    new MockRepository($this->em, $this->cachePath, true);
    $cacheFile = $this->cachePath . '/mitsuki_repo_map.php';

    expect($this->fs->exists($cacheFile))->toBeTrue();

    $map = include $cacheFile;
    expect($map[MockRepository::class])->toBe(MockEntity::class);
});

test('it prioritizes existing cache file over reflection', function () {
    $cacheFile = $this->cachePath . '/mitsuki_repo_map.php';

    // Create a "poisoned" cache file to verify the repo reads from it
    $content = "<?php return ['" . MockRepository::class . "' => 'FakeEntityNamespace'];";
    $this->fs->dumpFile($cacheFile, $content);

    $repo = new MockRepository($this->em, $this->cachePath, true);

    // Verify the value via reflection (setAccessible is not needed in PHP 8.1+)
    $reflection = new ReflectionClass($repo);
    $prop = $reflection->getProperty('entityClass');

    expect($prop->getValue($repo))->toBe('FakeEntityNamespace');
});

/**
 * CRUD OPERATIONS TESTS
 */

test('save() persists and flushes the entity', function () {
    $repo = new MockRepository($this->em, $this->cachePath, false);
    $entity = new MockEntity();

    $this->em->shouldReceive('persist')->once()->with($entity);
    $this->em->shouldReceive('flush')->once();

    $repo->save($entity);
});

test('delete() removes and flushes the entity', function () {
    $repo = new MockRepository($this->em, $this->cachePath, false);
    $entity = new MockEntity();

    $this->em->shouldReceive('remove')->once()->with($entity);
    $this->em->shouldReceive('flush')->once();

    $repo->delete($entity);
});

test('find() delegates to EntityManager with correct class', function () {
    $repo = new MockRepository($this->em, $this->cachePath, false);
    $mockInstance = new MockEntity();

    $this->em->shouldReceive('find')
        ->once()
        ->with(MockEntity::class, 99)
        ->andReturn($mockInstance);

    expect($repo->find(99))->toBe($mockInstance);
});

test('count() delegates to entity repository', function () {
    $repo = new MockRepository($this->em, $this->cachePath, false);

    $this->em->shouldReceive('getRepository')->with(MockEntity::class)->andReturn($this->doctrineRepo);
    $this->doctrineRepo->shouldReceive('count')->with(['active' => true])->andReturn(42);

    expect($repo->count(['active' => true]))->toBe(42);
});

/**
 * ADVANCED QUERY TESTS
 */

test('whereAnd() applies multiple andWhere conditions to QueryBuilder', function () {
    $repo = new MockRepository($this->em, $this->cachePath, false);
    $qb = mock(QueryBuilder::class);

    $this->em->shouldReceive('createQueryBuilder')->andReturn($qb);
    $qb->shouldReceive('select')->andReturnSelf();
    $qb->shouldReceive('from')->andReturnSelf();

    $qb->shouldReceive('andWhere')->twice()->andReturnSelf();
    $qb->shouldReceive('setParameter')->twice()->andReturnSelf();

    $result = $repo->whereAnd(['status' => 'active', 'role' => 'admin']);
    expect($result)->toBeInstanceOf(QueryBuilder::class);
});

test('paginate() returns a configured Doctrine Paginator', function () {
    $repo = new MockRepository($this->em, $this->cachePath, false);
    $qb = mock(QueryBuilder::class);
    $query = mock(Query::class);

    $this->em->shouldReceive('createQueryBuilder')->andReturn($qb);
    $qb->shouldReceive('select', 'from', 'setFirstResult', 'setMaxResults')->andReturnSelf();
    $qb->shouldReceive('getQuery')->andReturn($query);

    $paginator = $repo->paginate(2, 10);
    expect($paginator)->toBeInstanceOf(Paginator::class);
});

/**
 * RELATIONSHIP MANAGEMENT TESTS
 */

test('addRelated() handles Doctrine Collections and saves the parent entity', function () {
    $repo = new MockRepository($this->em, $this->cachePath, false);
    $entity = new MockEntity();
    $tag = new stdClass();

    $mockCollection = mock(\Doctrine\Common\Collections\Collection::class);

    $mockCollection->shouldReceive('add')->once()->with($tag)->andReturn(true);
    $mockCollection->shouldReceive('getIterator')->andReturn(new ArrayIterator([]));

    $entity->tags = $mockCollection;

    $this->metadata->shouldReceive('hasAssociation')->with('tags')->andReturn(true);
    $this->em->shouldReceive('persist')->once()->with($entity);
    $this->em->shouldReceive('flush')->once();

    $repo->addRelated($entity, 'tags', $tag);
});
