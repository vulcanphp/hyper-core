# HyperCore
Core Classes and Functionalities for Hyper MVT Framework

## Introduction

**HyperCore** is the backbone of the Hyper MVT Framework, providing essential core classes, utility functions, and helper methods to streamline web development. This document details all available classes and functions within the HyperCore.

## Core Classes

### Application
- **Class:** `application`
- **Description:** Application container that manages the overall lifecycle of your application.

### Database
- **Class:** `database`
- **Description:** PDO database container for managing database connections and operations.
- **Example:**
    ```php
    use hyper\database;
    $database = new database([
        'driver' => 'sqlite', // mysql
        'file' => __DIR__ . '/../sqlite.db', // required when driver is sqlite
        'host' => 'localhost',
        'user' => '{user}',
        'password' => '{password}',
        'port' => 3306,
        'name' => 'dbname',
        'charset' => 'utf8mb4'
    ]);
    var_dump($database->prepare('SELECT * FROM students'));
    ```

### Debugger
- **Class:** `debugger`
- **Description:** Error tracer and log viewer for debugging and logging application errors.

### Request
- **Class:** `request`
- **Description:** HTTP request class for handling and processing incoming requests.

### Response
- **Class:** `response`
- **Description:** HTTP response class for managing outgoing responses.

### Middleware
- **Class:** `middleware`
- **Description:** Middleware class for handling HTTP request routing.
- **Example:**
    ```php
    $middleware = new middleware();
    $middleware->add(callback);
    $middleware->handle(request: $request);
    ```

### Router
- **Class:** `router`
- **Description:** Router class for defining and handling application routes.
- **Example:**
    ```php
    $router = new router(middleware: $middleware);
    $router->add('/', fn() => 'Hello World');
    $response = $router->dispatch();
    $response->send();
    ```

### Model
- **Class:** `model`
- **Description:** Model class for handling database interactions with ORM and form handling.
- **Example:**
    ```php
    use hyper\model;

    class student extends model {
        protected string $table = 'students';

        public string $name;
        public int $age;
        public string $department;
    }

    dump(student::get()->result());
    ```

### Query
- **Class:** `query`
- **Description:** PHP PDO query builder for constructing and executing database queries.
- **Example:**
    ```php
    use hyper\query;
    $query = new query(database: $database, table: 'students');
    dump($query->result());
    ```

### Session
- **Class:** `session`
- **Description:** Session class for managing user sessions.

### Template
- **Class:** `template`
- **Description:** Template engine class for rendering views.
- **Example:**
    ```php
    use hyper\template;
    $engine = new template(__DIR__);
    var_dump($engine->render('welcome', ['message' => 'Welcome to App']));
    ```

### Translator
- **Class:** `translator`
- **Description:** Google Translator class for translating text.

## Utility Classes

### Cache
- **Class:** `cache`
- **Description:** Cache class for caching data.
- **Example:**
    ```php
    use hyper\cache;
    $cache = new cache('home');
    var_dump($cache->load('time', fn() => 'Now :' . time(), '5 minutes'));
    ```

### Collect
- **Class:** `collect`
- **Description:** Collection class for handling data collections.

### Form
- **Class:** `form`
- **Description:** Form builder class for constructing and managing forms.
- **Example:**
    ```php
    // independent usage
    $form = new form(request: $request, fields: [['type' => 'text', 'name' => 'name']]);
    // usage from model
    $form = new form(request: $request, model: $student);

    // add new form field
    $form->add(['type' => 'email', 'name' => 'email', 'required' => true]);
    
    // validate form
    if($form->validate()){
        var_dump($form->getData());
    }else{
        echo $form->render();
    }
    ```

### Hash
- **Class:** `hash`
- **Description:** Class for hashing and encryption.

### Image
- **Class:** `image`
- **Description:** Image helper class for managing image operations.
- **Example:**
    ```php
    use hyper\utils\image;
    $image = new image(__DIR__ . '/image.png');
    $image->compress(50);
    $image->resize(720, 360);
    $image->rotate(90);
    $image->bulkResize([540 => 540, 60 => 60]);
    ```

### Paginator
- **Class:** `paginator`
- **Description:** Paginator class for handling pagination.
- **Example:**
    ```php
    use hyper\utils\paginator;
    $paginator = new paginator(total: 500, limit: 20);
    $paginator->setData([...]);
    var_dump($paginator->getData(), $paginator->getLinks());
    ```

### Ping
- **Class:** `ping`
- **Description:** HTTP ping/cURL helper class.
- **Example:**
    ```php
    use hyper\utils\ping;
    $http = new ping();
    $http->download(__DIR__.'/downloads/file.jpg');
    var_dump($http->get('http://domain.com/download-file'));
    ```

### Uploader
- **Class:** `uploader`
- **Description:** File uploader class for managing file uploads.
- **Example:**
    ```php
    use hyper\utils\uploader;
    $uploader = new uploader(uploadDir: __DIR__ .'/uploads', extensions: ['jpe', 'png'], multiple: true);
    var_dump($uploader->upload($_FILES['upload']));
    ```

### Validator
- **Class:** `validator`
- **Description:** HTTP input validator class.
- **Example:**
    ```php
    use hyper\utils\validator;
    $validator = new validator(request: $request);
    var_dump($validator->validate([
        'name' => ['required', 'min:10', 'max:60'],
        'email' => ['required', 'email'],
    ]));
    ```

## Helper Classes

### Form
- **Class:** `form`
- **Description:** Trait for model to extract form fields from model object properties.
- **Example:**
    ```php
    use hyper\model;
    use hyper\helpers\form;

    class student extends model {
        use form;

        protected function form(): array {
            return [
                'name' => ['type' => 'text', 'required' => true],
                'email' => ['type' => 'email', 'required' => true],
                'gender' => ['type' => 'radio', 'options' => ['M' => 'Male', 'F' => 'Female']],
            ];
        }
    }

    $form = new form(request: $request, model: new student());
    var_dump($form->render());
    ```

### Mail
- **Class:** `mail`
- **Description:** PHP built-in mail class.
- **Example:**
    ```php
    use hyper\helpers\mail;
    $mail = new mail();
    $mail->from('shahin.moyshan2@gmail.com', 'Shahin Moyshan');
    $mail->replyTo('shahin.moyshan2@gmail.com');
    $mail->subject('Test Mail');
    $mail->body('Hello World, This is Test Mail From Shahin Moyshan');
    $mail->send();
    ```

### ORM
- **Class:** `orm`
- **Description:** Object-Relational Mapper for database interactions.
- **Example:**
    ```php
    use hyper\model;
    use hyper\helpers\orm;

    class student extends model {
        use orm;

        protected function orm(): array {
            return [
                'department' => ['has' => 'one', 'model' => department::class],
                'subjects' => ['has' => 'many-x', 'model' => subject::class, 'table' => 'students_subjects'],
                'results' => ['has' => 'many', 'model' => result::class, 'formIgnore' => true],
            ];
        }
    }

    var_dump(student::with(['subjects', 'results', 'department'])->paginate(20));
    ```

### Uploader
- **Class:** `uploader`
- **Description:** Uploader helper for managing model uploads.
- **Example:**
    ```php
    use hyper\model;
    use hyper\helpers\uploader;

    class student extends model {
        use uploader;

        protected function uploader(): array {
            return [
                [
                    'name' => 'photo',
                    'multiple' => false,
                    'uploadTo' => 'students',
                    'maxSize' => 1048576, // 1MB
                    'compress' => 75,
                    'resize' => [540 => 540],
                    'resizes' => [140 => 140, 60 => 60]
                ]
            ];
        }
    }

    var_dump(student::with(['subjects', 'results', 'department'])->paginate(20));
    ```

### Vite
- **Class:** `vite`
- **Description:** Vite helper class for asset management.

## Shortcut Functions

### Application
- **Function:** `app()`
- **Description:** Returns the application instance.

### Request
- **Function:** `request()`
- **Description:** Returns the request instance.

### Response
- **Function:** `response()`
- **Description:** Returns the response instance.

### Redirect
- **Function:** `redirect()`
- **Description:** Redirects to a different URL and returns void.

### Session
- **Function:** `session()`
- **Description:** Returns the session instance.

### Router
- **Function:** `router()`
- **Description:** Returns the router instance.

### Database
- **Function:** `database()`
- **Description:** Returns the database instance.

### Query
- **Function:** `query()`
- **Description:** Returns the query builder instance.

### Template
- **Function:** `template()`
- **Description:** Returns a new template instance.

### URLs
- **Functions:**
  - `url(string $path = ''): string` - Returns the URL for the given path.
  - `public_url(string $path = ''): string` - Returns the public URL for the given path.
  - `asset_url(string $path = ''): string` - Returns the asset URL for the given path.
  - `media_url(string $path = ''): string` - Returns the media URL for the given path.
  - `request_url()` - Returns the current requested URL.
  - `route_url()` - Returns the route URL.

### Directories
- **Functions:**
  - `app_dir()` - Returns the application directory.
  - `root_dir()` - Returns the root directory.

### Debugging
- **Functions:**
  - `dump()` - Dumps data for inspection.
  - `dd()` - Dumps data and stops execution.
  - `debugger()` - Shows any error details and logs application steps.

### Environment
- **Function:** `env()`
- **Description:** Gets an environment variable.

### CSRF
- **Functions:**
  - `csrf_token()` - Returns the CSRF token.
  - `csrf()` - Returns the CSRF token with an HTML hidden input.

### User
- **Function:** `user()`
- **Description:** Returns the logged-in user.

### Collections
- **Function:** `collect()`
- **Description:** Returns a new collect class instance.

### Cache
- **Function:** `cache()`
- **Description:** Returns a new cache class instance.

### Translation
- **Function:** `__()`
- **Description:** Translates text.

### Vite
- **Function:** `vite()`
- **Description:** Returns a new vite class instance.

### Templates
- **Function:** `template_exists()`
- **Description:** Checks if a template exists.

## Conclusion

HyperCore provides a comprehensive set of core functionalities, utility methods, and helper classes to make web development with the Hyper MVT Framework efficient and enjoyable. Whether you are handling HTTP requests, managing databases, or working with templates, HyperCore has you covered.
