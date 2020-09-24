<?php
declare(strict_types = 1);

namespace App\Component\DebugBar\Integration;

use PDO;
use PDOStatement;

class ExtendsPdoStatement extends PDOStatement
{
    /**
     * @var ExtendsPdo
     */
    protected $pdo;

    /**
     * @var array
     */
    protected $params = [];

    protected function __construct(ExtendsPdo $pdo)
    {
        $this->pdo = $pdo;
    }

    public function bindParam($parameter, &$variable, $data_type = PDO::PARAM_STR, $length = null, $driver_options = null)
    {
        $this->params[$parameter] = $variable;
        $args = [$parameter, &$variable] + func_get_args();
        return call_user_func_array(['parent', __FUNCTION__], $args);
    }

    public function bindValue($parameter, $value, $data_type = PDO::PARAM_STR)
    {
        $this->params[$parameter] = $value;
        $args = func_get_args();
        return call_user_func_array(['parent', __FUNCTION__], $args);
    }

    public function execute($input_parameters = null)
    {
        $params = $this->params;
        if (is_array($input_parameters)) {
            $params = array_merge($params, $input_parameters);
        }
        return $this->pdo->callProfiling($this->queryString, $params, function () use ($input_parameters) {
            return parent::execute($input_parameters);
        });
    }
}
