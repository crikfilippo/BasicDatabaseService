# BasicDatabaseService

Simple PHP class for MySQL database operations with prepared statements.

## Setup

### 1. Initialize Connection

Set database parameters once at application start:

```php
use DB\DB;

DB::setParams(
    host: 'localhost',
    port: 3306,
    dbname: 'myapp',
    user: 'root',
    pass: 'password'
);
```

Or via environment variables (`DBHOST`, `DBPORT`, `DBNAME`, `DBUSER`, `DBPSW`):

```php
DB::setParams();
```

## Usage

### SELECT - Get All Results

**Static:**
```php
$users = DB::query('SELECT * FROM users WHERE status = :status', ['status' => 'active'])->get();
```

**Object:**
```php
$db = new DB();
$users = $db->query('SELECT * FROM users WHERE status = :status', ['status' => 'active'])->get();
```

Returns array of objects (default: `stdClass`) or associative arrays:

```php
// As objects
$users = DB::query('SELECT * FROM users')->get(); // stdClass
$users = DB::query('SELECT * FROM users')->get(PDO::FETCH_ASSOC); // array

// Access data
foreach ($users as $user) {
    echo $user->name; // object notation
    // or
    echo $user['name']; // array notation
}
```

### SELECT - Get First Result

**Static:**
```php
$user = DB::query('SELECT * FROM users WHERE id = :id', ['id' => 1])->first();
```

**Object:**
```php
$db = new DB();
$user = $db->query('SELECT * FROM users WHERE id = :id', ['id' => 1])->first();
```

Returns single object/array or `null` if not found.

### INSERT

**Static:**
```php
$insertId = DB::query(
    'INSERT INTO users (name, email) VALUES (:name, :email)',
    ['name' => 'John', 'email' => 'john@example.com']
)->execute();

echo $insertId; // Last inserted ID
```

**Object:**
```php
$db = new DB();
$insertId = $db->query(
    'INSERT INTO users (name, email) VALUES (:name, :email)',
    ['name' => 'John', 'email' => 'john@example.com']
)->execute();
```

### UPDATE

**Static:**
```php
$affectedRows = DB::query(
    'UPDATE users SET status = :status WHERE id = :id',
    ['status' => 'inactive', 'id' => 1]
)->execute();

echo $affectedRows; // Rows updated
```

**Object:**
```php
$db = new DB();
$affectedRows = $db->query(
    'UPDATE users SET status = :status WHERE id = :id',
    ['status' => 'inactive', 'id' => 1]
)->execute();
```

### DELETE

**Static:**
```php
$deletedRows = DB::query(
    'DELETE FROM users WHERE id = :id',
    ['id' => 1]
)->execute();
```

**Object:**
```php
$db = new DB();
$deletedRows = $db->query(
    'DELETE FROM users WHERE id = :id',
    ['id' => 1]
)->execute();
```

### PAGINATION

**Static:**
```php
$paginated = DB::query(
    'SELECT * FROM users ORDER BY id DESC'
)->paginate(page : 1, perPage: 10);

//page && perPage ints : specific page number and number of results
//page && perPage strings : GET params name used to obtain paginating params
//page || perPage nulls : defaule GET params name to obtain paginating params
//fallback to page 1, perPage 15

// Access paginated data
echo "Total: " . $paginated->total;
echo "Page: " . $paginated->currentPage . "/" . $paginated->lastPage;

foreach ($paginated->items as $user) {
    echo $user->name;
}
```

Page comes from `$_GET['page']` parameter or specify manually:

```php
$paginated = DB::query(
    'SELECT * FROM users ORDER BY id DESC'
)->paginate(perPage: 10, page: 2);
```

**Object:**
```php
$db = new DB();
$paginated = $db->query(
    'SELECT * FROM users ORDER BY id DESC'
)->paginate(perPage: 10);
```

### Column Mapping

Map database columns to different attribute names:

```php
$users = DB::query('SELECT id, user_name, user_email FROM users')->get(
    mode: PDO::FETCH_OBJ,
    mapColumnToAttribute: [
        'user_name' => 'name',
        'user_email' => 'email'
    ]
);

// Result: $users[0]->name (mapped from user_name)
```

## Common Patterns

### Check if Record Exists

```php
$result = DB::query('SELECT id FROM users WHERE email = :email', ['email' => 'test@example.com'])->first();

if ($result) {
    echo "User exists";
} else {
    echo "User not found";
}
```

### Batch Operations

```php
$users = [
    ['name' => 'Alice', 'email' => 'alice@example.com'],
    ['name' => 'Bob', 'email' => 'bob@example.com'],
];

foreach ($users as $user) {
    DB::query(
        'INSERT INTO users (name, email) VALUES (:name, :email)',
        $user
    )->execute();
}
```

### Get Count

```php
$count = DB::query('SELECT COUNT(*) as total FROM users')->first(PDO::FETCH_ASSOC);
echo $count['total'];
```

## Features

- Prepared statements (SQL injection prevention)
- Static and object-oriented usage
- Flexible result modes (objects or arrays)
- Column mapping/transformation
- Built-in pagination
- Sensitive parameter masking in error logs
