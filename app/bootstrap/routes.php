<?php
declare(strict_types = 1);

namespace App;

use App\Component\DebugBar\Bridge\Doctrine\SqlLoggerBridge;
use App\Component\DebugBar\Bridge\Pdo\ExtendsPdo;
use App\Component\DebugBar\DataCollector\DatabaseCollector;
use DebugBar\DataCollector\MessagesCollector;
use DebugBar\DataCollector\TimeDataCollector;
use Doctrine\DBAL\DriverManager;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Laminas\Diactoros\Response\TextResponse;
use PDO;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

return [
    'routes' => function (
        Renderer $renderer,
        MessagesCollector $messagesCollector,
        TimeDataCollector $timeDataCollector,
        DatabaseCollector $databaseCollector
    ) {
        $routes = [];

        $routes['get']['/'] = function () use ($renderer, $messagesCollector, $timeDataCollector): ResponseInterface {
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
            return new RedirectResponse('/');
        };

        $routes['get']['/exception'] = function () use ($messagesCollector): ResponseInterface {
            $messagesCollector->addMessage('Exception!!!');
            throw new RuntimeException('oops!!!');
        };

        $routes['get']['/ajax'] = function () use ($messagesCollector): ResponseInterface {
            $messagesCollector->addMessage('Ajax!!!');
            return new JsonResponse(['abc' => 123]);
        };

        $routes['get']['/pdo'] = function () use ($renderer, $databaseCollector): ResponseInterface {

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
            } catch (Throwable $ex) {
            }

            return $renderer();
        };

        $routes['get']['/doctrine'] = function () use ($renderer, $databaseCollector, $timeDataCollector): ResponseInterface {

            $connection = DriverManager::getConnection([
                'host'          => '127.0.0.1',
                'port'          => 5432,
                'dbname'        => 'test',
                'user'          => 'test',
                'password'      => 'pass',
                'charset'       => 'utf8',
                'driver'        => 'pdo_pgsql',
                'driverOptions' => [
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ],
            ]);
            $connection->getConfiguration()->setSQLLogger(
                new SqlLoggerBridge($connection, $databaseCollector, $timeDataCollector)
            );

            $connection->query("select 'this is doctrine'");

            $connection->prepare("select :aaa, :bbb, :ccc, :ddd, :eee")
                ->execute(['aaa' => 'AAA', 'bbb' => 123, 'ccc' => null, 'ddd' => true, 'eee' => false]);

            $connection->exec('drop table if exists t');
            $connection->exec('create table t (id serial not null primary key)');

            $connection->beginTransaction();
            $connection->prepare('insert into t (id) values (?)')->execute([$id = 123]);

            $stmt = $connection->prepare('select * from t where id = ?');
            $result = $stmt->executeQuery([$id]);
            $row = $result->fetchAssociative();
            assert(!!$row);

            $connection->commit();

            return $renderer();
        };

        $routes['get']['/download'] = function () use ($messagesCollector): ResponseInterface {
            $messagesCollector->addMessage('download now');

            return new TextResponse('download now', 200, [
                'content-disposition' => 'attachment; filename=download.txt'
            ]);
        };

        return $routes;
    },
];
