<?php 

namespace Tests\Fixtures;

use Mitsuki\ORM\Repositories\Repository;

class MockRepository extends Repository {
    protected MockEntity $mockEntity;
}