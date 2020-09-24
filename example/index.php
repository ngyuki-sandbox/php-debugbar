<?php
declare(strict_types = 1);

namespace App\Component\DebugBar\Example;

require __DIR__ . '/../vendor/autoload.php';

use App\Component\DebugBar\DataCollector\DatabaseCollector;
use App\Component\DebugBar\DataCollector\SettingDataCollector;
use App\Component\DebugBar\Integration\DoctrineSQLLogger;
use App\Component\DebugBar\Integration\ExtendsPdo;
use App\Component\DebugBar\Middleware\DebugbarMiddleware;
use App\Component\DebugBar\Storage\GarbageableFileStorage;
use DebugBar\DataCollector\ConfigCollector;
use DebugBar\DataCollector\ExceptionsCollector;
use DebugBar\DataCollector\MemoryCollector;
use DebugBar\DataCollector\MessagesCollector;
use DebugBar\DataCollector\RequestDataCollector;
use DebugBar\DataCollector\TimeDataCollector;
use DebugBar\DebugBar;
use Doctrine\DBAL\DriverManager;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\StreamFactory;
use Laminas\HttpHandlerRunner\Emitter\SapiStreamEmitter;
use Laminas\Stratigility\MiddlewarePipe;
use Laminas\Stratigility\Middleware\CallableMiddlewareDecorator;
use Laminas\Stratigility\Middleware\NotFoundHandler;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Throwable;

$debugbar = new DebugBar();

$debugbar->addCollector((new RequestDataCollector())->useHtmlVarDumper());
$debugbar->addCollector(new MemoryCollector());
$debugbar->addCollector($messagesCollector = new MessagesCollector());
$debugbar->addCollector($timeDataCollector = new TimeDataCollector());
$debugbar->addCollector(
    $databaseCollector = (new DatabaseCollector(__DIR__ . '/../'))->setBacktraceLimit(4)->useExplain(true)
);
$debugbar->addCollector(new ConfigCollector(['foo' => 'bar'], 'AppConfig'));
$debugbar->addCollector(new SettingDataCollector());
$debugbar->addCollector($exceptionsCollector = new ExceptionsCollector());

$debugbar->setStorage(new GarbageableFileStorage(__DIR__ . '/../data/'));

///

$responseFactory = new ResponseFactory();
$streamFactory = new StreamFactory();
$routes = [];

$renderer = function (array $params = []) {
    extract($params);
    ob_start();
    require __DIR__ . '/index.html.php';
    return new Response\HtmlResponse(ob_get_clean());
};

$pipeline = new MiddlewarePipe();

$pipeline->pipe(new DebugbarMiddleware($debugbar, $responseFactory, $streamFactory));

$pipeline->pipe(new CallableMiddlewareDecorator(function (
    ServerRequestInterface $request,
    RequestHandlerInterface $handler
) use ($renderer, $exceptionsCollector) : ResponseInterface {
    try {
        return $handler->handle($request);
    } catch (Throwable $ex) {
        $exceptionsCollector->addThrowable($ex);
        return $renderer(['exception' => $ex])->withStatus(500);
    }
}));

$pipeline->pipe(new CallableMiddlewareDecorator(
    function (ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
        session_start();
        $headers = headers_list();
        $response = $handler->handle($request);
        foreach ($headers as $header) {
            list($name, $value) = explode(':', $header, 2);
            header_remove($name);
            $response = $response->withHeader($name, $value);
        }
        return $response;
    })
);

$routes['get']['/'] = function () use ($renderer, $messagesCollector, $timeDataCollector) : ResponseInterface {
    $messagesCollector->addMessage('hello world!');

    $timeDataCollector->startMeasure('hoge', 'hogehoge');
    usleep(10000);
    $timeDataCollector->stopMeasure('hoge');

    $timeDataCollector->measure('longlong', function () {
        usleep(10000);
    });

    return $renderer();
};

$routes['post']['/'] = function () use ($messagesCollector) : ResponseInterface {
    $messagesCollector->addMessage('Redirect!!!');
    return new Response\RedirectResponse('/');
};

$routes['get']['/exception'] =  function () use ($messagesCollector) : ResponseInterface {
    $messagesCollector->addMessage('Exception!!!');
    throw new RuntimeException('oops!!!');
};

$routes['get']['/ajax'] =  function () use ($messagesCollector) : ResponseInterface {
    $messagesCollector->addMessage('Ajax!!!');
    return new Response\JsonResponse(['abc' => 123]);
};

$routes['get']['/pdo'] = function () use ($renderer, $databaseCollector) : ResponseInterface {

    $pdo = new ExtendsPdo(
        'mysql:host=127.0.0.1;port=13306;dbname=test;charset=utf8',
        'test',
        'pass',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]
    );
    $pdo->setDatabaseCollector($databaseCollector);

    $pdo->prepare('select :aaa, :bbb, :ccc, :ddd, :eee')
        ->execute(['aaa' => 'AAA', 'bbb' => 123, 'ccc' => null, 'ddd' => true, 'eee' => false]);

    $pdo->query('select 123');
    $pdo->exec('drop table if exists t');
    $pdo->exec('create table t (id int not null primary key auto_increment)');

    $pdo->beginTransaction();
    $pdo->prepare('insert into t (id) values (?)')->execute([123]);
    $pdo->exec('insert into t (id) values (null)');
    $id = $pdo->query('select last_insert_id()')->fetchColumn(0);
    assert($id == 124);

    $stmt = $pdo->prepare('select * from t where id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    assert(!!$row);

    $pdo->commit();

    $pdo->query('select SQL_CALC_FOUND_ROWS * from t limit 1');
    $num = $pdo->query('select FOUND_ROWS()')->fetchColumn(0);
    assert($num == 2);

    try {
        $pdo->query("select 'error' from error");
    } catch (Throwable $ex) {}

    return $renderer();
};


$routes['get']['/doctrine'] = function () use ($renderer, $databaseCollector, $timeDataCollector) : ResponseInterface {

    $connection = DriverManager::getConnection([
        'host'     => '127.0.0.1',
        'port'     => 13306,
        'dbname'   => 'test',
        'user'     => 'test',
        'password' => 'pass',
        'charset'  => 'utf8',
        'driver' => 'pdo_mysql',
        'driverOptions' => [
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ],
    ]);
    $connection->getConfiguration()->setSQLLogger(
        new DoctrineSQLLogger($connection, $databaseCollector, $timeDataCollector)
    );

    $connection->query("select 'this is doctrine'");

    $connection->prepare("select :aaa, :bbb, :ccc, :ddd, :eee")
        ->execute(['aaa' => 'AAA', 'bbb' => 123, 'ccc' => null, 'ddd' => true, 'eee' => false]);

    $connection->exec('drop table if exists t');
    $connection->exec('create table t (id int not null primary key auto_increment)');

    $connection->beginTransaction();
    $connection->prepare('insert into t (id) values (?)')->execute([123]);
    $connection->exec('insert into t (id) values (null)');
    $id = $connection->query('select last_insert_id()')->fetchColumn(0);
    assert($id == 124);

    $stmt = $connection->prepare('select * from t where id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    assert(!!$row);

    $connection->commit();

    $connection->query('select SQL_CALC_FOUND_ROWS * from t limit 1');
    $num = $connection->query('select FOUND_ROWS()')->fetchColumn(0);
    assert($num == 2);

    return $renderer();
};

$pipeline->pipe(new CallableMiddlewareDecorator(
    function (ServerRequestInterface $request, RequestHandlerInterface $handler) use ($routes) : ResponseInterface {
        $method = strtolower($request->getMethod());
        $path = $request->getUri()->getPath();
        if (isset($routes[$method][$path])) {
            return ($routes[$method][$path])($request, $handler);
        }
        return $handler->handle($request);
    }
));

$pipeline->pipe(new NotFoundHandler(function () use ($responseFactory) {
    return $responseFactory->createResponse();
}));

$request = ServerRequestFactory::fromGlobals();
$response = $pipeline->handle($request);
$emitter = new SapiStreamEmitter();
$emitter->emit($response);
