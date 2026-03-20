# Mitsuki ORM

<p align="center">
  <strong>⚡ A lightweight, high-performance ORM wrapper for Doctrine 3</strong>
</p>

<p align="center">
  Simplify your repositories. Eliminate boilerplate. Boost performance.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.1+-blue?style=flat-square" />
  <img src="https://img.shields.io/badge/Doctrine-ORM%203-orange?style=flat-square" />
  <img src="https://img.shields.io/badge/License-MIT-green?style=flat-square" />
  <img src="https://img.shields.io/badge/Tests-Passing-brightgreen?style=flat-square" />
  <img src="https://img.shields.io/badge/Coverage-High-success?style=flat-square" />
</p>

---

## 📖 Overview

**Mitsuki ORM** is a developer-friendly wrapper around Doctrine ORM 3 that removes repetitive repository configuration.

It uses **reflection-based entity discovery**, **filesystem caching**, and **fluent query helpers** to deliver both **developer experience** and **performance**.

---

## ✨ Features

* 🔍 **Automatic Entity Discovery** (zero config)
* ⚡ **Filesystem Cache for Production**
* 🧠 **Smart Reflection Mapping**
* 🔗 **Relationship Helpers**
* 📄 **Pagination Ready (Doctrine Paginator)**
* 🧪 Fully tested with Pest PHP & Mockery
* 🛠️ CLI integration via `mitsuki/commands`

---

## 📦 Installation

```bash
composer require mitsuki/mitsuki-orm
```

---

## 🚀 Quick Start

### 1. Create a Repository

```php
namespace App\Repository;

use Mitsuki\ORM\Repositories\Repository;
use App\Entity\User;

class UserRepository extends Repository
{
    protected User $userEntity;
}
```

✅ No configuration needed — Mitsuki automatically detects the entity.

---

## 🧱 Basic Usage

```php
// Create
$userRepository->save($user);

// Read
$user = $userRepository->find(1);

// Update (same as save)
$userRepository->save($user);

// Delete
$userRepository->delete($user);

// All
$users = $userRepository->findAll();
```

---

## 🔍 Query Builder Helpers

### Simple Query

```php
$userRepository->where([
    'status' => 'active'
]);
```

### AND Conditions

```php
$qb = $userRepository->whereAnd([
    'status' => 'active',
    'role' => 'admin'
]);

$results = $qb->getQuery()->getResult();
```

### OR Conditions

```php
$qb = $userRepository->whereOr([
    'role' => 'admin',
    'role' => 'editor'
]);
```

---

## 📄 Pagination

```php
$paginator = $userRepository->paginate(page: 1, limit: 10);

foreach ($paginator as $user) {
    // ...
}
```

---

## 🔗 Relationship Management

### Get Collection

```php
$posts = $userRepository->getCollection($user, 'posts');
```

### Add Related Entity

```php
$userRepository->addRelated($user, 'posts', $post);
```

### Get Single Relation

```php
$profile = $userRepository->getRelated($user, 'profile');
```

---

## ⚡ Performance Optimization

Enable caching in production:

```php
$repo = new UserRepository(
    entityManager: $entityManager,
    cachePath: '/path/to/cache',
    useCache: true
);
```

### Cache Strategy

| Step | Description         |
| ---- | ------------------- |
| 1    | In-memory cache     |
| 2    | Filesystem cache    |
| 3    | Reflection fallback |
| 4    | Cache warmup        |

---

## 🧹 CLI Commands

```bash
php hermite repository:clear
```

### Behavior

* ✅ Removes cache file
* ℹ️ Shows info if no cache exists
* ❌ Returns error on failure

---

## 🧪 Testing

```bash
composer test
```

✔ Covers:

* Cache system
* CLI commands
* Repository logic
* Error scenarios

---

## 🏗️ Architecture

```
Repository Pattern
    ↓
Reflection Mapping
    ↓
Filesystem Cache
    ↓
Doctrine QueryBuilder
```

---

## 📁 Project Structure

```
src/
 ├── ORM/
 │   ├── Repositories/
 │   │   └── Repository.php
 │   ├── Command/
 │   │   └── RepositoryClearCommand.php
tests/
```

---

## ⚠️ Requirements

* PHP 8.1+
* Doctrine ORM 3+
* Symfony Filesystem

---

## 📜 License

MIT License — free for personal and commercial use.

---

## 👨‍💻 Author

**Zgenius Matondo**
📧 [zgeniuscoders@gmail.com](mailto:zgeniuscoders@gmail.com)

---

## ⭐ Support the Project

If you like this project:

* ⭐ Star the repository
* 🐛 Report issues
* 🤝 Contribute

---

## 🔥 Roadmap (Optional but Pro Touch)

* [ ] Soft delete support
* [ ] Query caching layer
* [ ] Event system (hooks)
* [ ] Multi-tenant support
* [ ] API Platform integration

---

## 💡 Final Thought

> Mitsuki ORM is built for developers who love Doctrine — but hate boilerplate.

---

[![Latest Version](https://img.shields.io/packagist/v/mitsuki/mitsuki-orm.svg)]()
[![Downloads](https://img.shields.io/packagist/dt/mitsuki/mitsuki-orm.svg)]()
[![License](https://img.shields.io/packagist/l/mitsuki/mitsuki-orm.svg)]()