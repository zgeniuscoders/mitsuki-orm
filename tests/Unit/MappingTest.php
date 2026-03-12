<?php

namespace Tests\Unit;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Tools\SchemaTool;
use Mitsuki\ORM\MitsukiORM;
use Tests\Fixtures\Article;
use Tests\Fixtures\Category;

/**
 * Setup: Initializes a clean physical SQLite database for each test.
 * * We use a physical file (db_test.sqlite) to ensure the driver handles 
 * real file-based IO correctly, mimicking a production environment.
 */
beforeEach(function () {
    $this->dbFile = __DIR__ . '/../db_test.sqlite';
    clearstatcache();

    $this->em = MitsukiORM::create([
        'driver' => 'pdo_sqlite',
        'path'   => $this->dbFile,
    ], __DIR__ . '/../../var/cache', true);

    $tool = new SchemaTool($this->em);
    $tool->createSchema($this->em->getMetadataFactory()->getAllMetadata());
});

/**
 * Teardown: Safely closes the database connection.
 * * Essential for Windows compatibility to release file locks on the SQLite database,
 * allowing the 'beforeEach' of the next test to delete and recreate the file.
 */
afterEach(function () {
    $this->em->getConnection()->close();
});

/**
 * Test: Reflection Mapping
 * Validates that class properties are correctly mapped to SQL types and primary keys.
 */
test('correctly maps simple fields from reflection', function () {
    $metadata = $this->em->getClassMetadata(Category::class);

    expect($metadata->getTableName())->toBe('categories')
        ->and($metadata->getFieldMapping('name')['type'])->toBe('string')
        ->and($metadata->getFieldMapping('id')['id'])->toBeTrue();
});

/**
 * Test: Relationship Retrieval
 * Verifies that the ORM can navigate through ManyToOne and ManyToMany links 
 * and hydrate related objects (Lazy Loading).
 */
test('automatically detects ManyToOne relationships', function () {
    $metadata = $this->em->getClassMetadata(Article::class);

    expect($metadata->hasAssociation('category'))->toBeTrue();

    $association = $metadata->getAssociationMapping('category');
    expect($association['targetEntity'])->toBe(Category::class)
        ->and($association['type'])->toBe(ClassMetadata::MANY_TO_ONE);
});

test('automatically detects ManyToMany via DocBlock', function () {
    $metadata = $this->em->getClassMetadata(Article::class);

    expect($metadata->hasAssociation('tags'))->toBeTrue();

    $association = $metadata->getAssociationMapping('tags');
    expect($association['type'])->toBe(ClassMetadata::MANY_TO_MANY)
        ->and($association['joinTable']['name'])->toBe('article_tags');
});

test('can persist and retrieve data using Mitsuki entities', function () {

    $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($this->em);
    $schemaTool->createSchema($this->em->getMetadataFactory()->getAllMetadata());

    $cat = new Category();
    $cat->name = 'Tech';

    $this->em->persist($cat);
    $this->em->flush();
    $this->em->clear();

    $found = $this->em->find(Category::class, 1);
    expect($found->name)->toBe('Tech');
});

test('correctly retrieves a ManyToOne relationship (Article -> Category)', function () {

    $metadatas = $this->em->getMetadataFactory()->getAllMetadata();
    $schemaTool = new SchemaTool($this->em);
    $schemaTool->createSchema($metadatas);

    $category = new Category();
    $category->name = 'Framework News';
    $this->em->persist($category);

    $article = new Article();
    $article->title = 'Introducing Tsuki ORM';
    $article->category = $category;
    $this->em->persist($article);

    $this->em->flush();
    $this->em->clear();

    $savedArticle = $this->em->find(Article::class, 1);

    expect($savedArticle->title)->toBe('Introducing Mutsiki ORM')
        ->and($savedArticle->category)->toBeInstanceOf(Category::class)
        ->and($savedArticle->category->name)->toBe('Framework News');
});

it('correctly retrieves a ManyToMany relationship (Article <-> Tags)', function () {

    $tag = new \Tests\Fixtures\Tag();
    $tag->label = 'PHP 8';
    $this->em->persist($tag);

    $tag2 = new \Tests\Fixtures\Tag();
    $tag2->label = 'Tsuki';
    $this->em->persist($tag2);

    $article = new \Tests\Fixtures\Article();
    $article->title = 'ManyToMany Test';

    $article->tags->add($tag);
    $article->tags->add($tag2);

    $this->em->persist($article);
    $this->em->flush();

    $savedId = $article->id;
    $this->em->clear();

    $result = $this->em->find(\Tests\Fixtures\Article::class, $savedId);

    expect($result->title)->toBe('ManyToMany Test')
        ->and($result->tags)->toHaveCount(2);

    $labels = $result->tags->map(fn($t) => $t->label)->toArray();
    expect($labels)->toContain('PHP 8', 'Tsuki');
});
