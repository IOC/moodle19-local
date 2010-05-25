<?php

class FormulaFunction {

    var $id;
    var $type;
    var $params_count;
    var $params_type;

    function __construct($id, $type, $params_count=false, $params_type=false) {
         $this->id = $id;
         $this->type = $type;
         $this->params_count = (is_numeric($params_count) ?
                                array($params_count) : $params_count);
         $this->params_type = $params_type;
    }

    function evaluate($args) {
    }
}

class FormulaFunctionBuiltin extends FormulaFunction {

    function evaluate($args) {
        return call_user_func_array($this->id, $args);
    }
}

class FormulaFunctionMod extends FormulaFunction {

    function __construct() {
        parent::__construct('mod', 'number', 2, 'number');
    }

    function evaluate($args) {
        return $args[0] % $args[1];
    }
}

class FormulaFunctionSum extends FormulaFunction {

    function __construct() {
        parent::__construct('sum', 'number', false, 'number');
    }

    function evaluate($args) {
        $result = 0.0;
        foreach ($args as $arg) {
            $result += $arg;
        }
        return $result;
    }
}

class FormulaFunctionAverage extends FormulaFunction {

    function __construct() {
        parent::__construct('average', 'number', false, 'number');
    }

    function evaluate($args) {
        $sum = 0.0;
        foreach ($args as $arg) {
            $sum += $arg;
        }
        return $sum / count($args);
    }
}


class FormulaFunctionPower extends FormulaFunction {

    function __construct() {
        parent::__construct('power', 'number', 2, 'number');
    }

    function evaluate($args) {
        return pow($args[0], $args[1]);
    }
}

class FormulaFunctionCount extends FormulaFunction {

    function __construct() {
        parent::__construct('count', 'number', false, 'boolean');
    }

    function evaluate($args) {
        $result = 0;
        foreach ($args as $arg) {
            $result += ($arg ? 1 : 0);
        }
        return $result;
    }
}

class FormulaFunctionFinalPAF extends FormulaFunction {

    function __construct() {
        parent::__construct('finalpaf', 'number', 2, 'number');
    }

    function evaluate($args) {
        list($nota1, $nota2) = $args;
        return ($nota2 ? $nota2 : $nota1);
    }
}

class FormulaFunctionFinalPAF1 extends FormulaFunction {

    function __construct() {
        parent::__construct('finalpaf1', 'number', 3, 'number');
    }

    function evaluate($args) {
        list($nota, $llindar, $bonus) = $args;
        return $nota + ($nota >= $llindar ? $bonus : 0);
    }
}

class FormulaFunctionFinalPAF2 extends FormulaFunction {

    function __construct() {
        parent::__construct('finalpaf2', 'number', 4, 'number');
    }

    function evaluate($args) {
        list($nota1, $nota2, $llindar, $bonus) = $args;
        return $nota2 + ((!$nota1 and $nota2 >= $llindar) ? $bonus : 0);
    }
}

class FormulaFunctionTable {

    var $functions;
    var $aliases;

    function __construct() {
        $this->builtin('pi', 'number', 0, 'number');
        $this->builtin('round', 'number', array(1, 2), 'number');
        $this->builtins(array('abs', 'sqrt', 'log', 'exp',
                              'sin', 'asin', 'sinh', 'asinh',
                              'cos', 'acos', 'cosh', 'acosh',
                              'tan', 'atan', 'tanh', 'atanh'),
                        'number', 1, 'number');
        $this->builtins(array('min', 'max'), 'number', false, 'number');
        $this->function_(new FormulaFunctionAverage());
        $this->function_(new FormulaFunctionMod());
        $this->function_(new FormulaFunctionSum());
        $this->function_(new FormulaFunctionPower());
        $this->function_(new FormulaFunctionCount());
        $this->function_(new FormulaFunctionFinalPAF());
        $this->function_(new FormulaFunctionFinalPAF1());
        $this->function_(new FormulaFunctionFinalPAF2());
        $this->aliases = array('arcsin' => 'asin', 'arcsinh' => 'asinh',
                               'arccos' => 'acos', 'arccosh' => 'acosh',
                               'arctan' => 'atan', 'arctanh' => 'atanh',
                               'ln' => 'log');
    }

    function function_($function) {
        $this->functions[$function->id] = $function;
    }

    function builtin($id, $type, $params_count, $params_type) {
        $this->function_(new FormulaFunctionBuiltin($id, $type, $params_count,
                                                    $params_type));
    }

    function builtins($ids, $type, $params_count, $params_type) {
        foreach ($ids as $id) {
            $this->builtin($id, $type, $params_count, $params_type);
        }
    }

    function get($id) {
        if (isset($this->aliases[$id])) {
            $id = $this->aliases[$id];
        }
        if (!isset($this->functions[$id])) {
            throw new FormulaException('syntax error');
        }
        return $this->functions[$id];
    }
}


function formula_function($id) {
    static $table = null;
    if (!$table) {
        $table = new FormulaFunctionTable();
    }
    return $table->get($id);
}

