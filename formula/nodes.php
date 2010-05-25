<?php

class FormulaNode {

    var $type;

    function check($type) {
        if ($this->type != $type) {
            throw new FormulaException('type error');
        }
    }

    function evaluate($values) {
    }

    function variables($vars=array()) {
        return $vars;
    }
}

class FormulaNodeUnary extends FormulaNode {

    var $node;

    function __construct($node) {
        $this->node = $node;
        $node->check($this->type);
    }

    function variables($vars=array()) {
        return $this->node->variables(&$vars);
    }

}

class FormulaNodeBinary extends FormulaNode {

    var $node_left;
    var $node_right;

    function __construct($node_left, $node_right) {
        $this->node_left = $node_left;
        $this->node_right = $node_right;
        $node_left->check($this->type);
        $node_right->check($this->type);
    }

    function variables($vars=array()) {
        $this->node_left->variables(&$vars);
        $this->node_right->variables(&$vars);
        return $vars;
    }

}

class FormulaNodeNary extends FormulaNode {

    var $nodes;

    function __construct($nodes) {
        $this->nodes = $nodes;
        foreach ($nodes as $node) {
            $node->check($this->type);
        }
    }

    function variables($vars=array()) {
        foreach ($this->nodes as $node) {
            $node->variables(&$vars);
        }
        return $vars;
    }
}

class FormulaNodeConditional extends FormulaNode {

    var $type = 'number';
    var $node_condition;
    var $node_if;
    var $node_else;

    function __construct($node_condition, $node_if, $node_else) {
        $this->node_condition = $node_condition;
        $this->node_if = $node_if;
        $this->node_else = $node_else;
        $node_condition->check('boolean');
        $node_if->check('number');
        $node_else->check('number');
    }

   function evaluate($values) {
        $node = ($this->node_condition->evaluate($values) ?
                 $this->node_if : $this->node_else);
        return $node->evaluate($values);
    }

    function variables($vars=array()) {
        $this->node_condition->variables(&$vars);
        $this->node_if->variables(&$vars);
        $this->node_else->variables(&$vars);
        return $vars;
    }

}

class FormulaNodeConjunction extends FormulaNodeNary {

    var $type = 'boolean';

    function evaluate($values) {
        $result = true;
        foreach ($this->nodes as $node) {
            $result = ($result and $node->evaluate($values));
        }
        return $result;
    }
}

class FormulaNodeDisjunction extends FormulaNodeNary {

    var $type = 'boolean';

    function evaluate($values) {
        $result = false;
        foreach ($this->nodes as $node) {
            $result = ($result or $node->evaluate($values));
        }
        return $result;
    }
}

class FormulaNodeComplement extends FormulaNodeUnary {

    var $type = 'boolean';

    function evaluate($values) {
        return ! $this->node->evaluate($values);
    }
}

class FormulaNodeComparison extends FormulaNodeBinary {

    var $type = 'boolean';
    var $operand;

    function __construct($operand, $node_left, $node_right) {
        $this->operand = $operand;
        $this->node_left = $node_left;
        $this->node_right = $node_right;
        $node_left->check('number');
        $node_right->check('number');
    }

    function evaluate($values) {
        $left = $this->node_left->evaluate($values);
        $right = $this->node_right->evaluate($values);

        switch ($this->operand) {
        case '=': return $left == $right;
        case '!=': return $left != $right;
        case '<': return $left < $right;
        case '>': return $left > $right;
        case '<=': return $left <= $right;
        case '>=': return $left >= $right;
        }
    }
}

class FormulaNodeSum extends FormulaNodeNary {

    var $type = 'number';

    function evaluate($values) {
        $result = 0.0;
        foreach ($this->nodes as $node) {
            $result += $node->evaluate($values);
        }
        return $result;
    }
}

class FormulaNodeProduct extends FormulaNodeNary {

    var $type = 'number';

    function evaluate($values) {
        $result = 1.0;
        foreach ($this->nodes as $node) {
            $result *= $node->evaluate($values);
        }
        return $result;
    }
}

class FormulaNodePower extends FormulaNodeBinary {

    var $type = 'number';

    function evaluate($values) {
        $base = $this->node_left->evaluate($values);
        $exp = $this->node_right->evaluate($values);
        if ($base < 0.0 and $exp < 1.0) {
            throw new FormulaException('eval error');
        }
        return pow($base, $exp);
    }
}

class FormulaNodeNegative extends FormulaNodeUnary {

    var $type = 'number';

    function evaluate($values) {
        return - $this->node->evaluate($values);
    }
}

class FormulaNodeInverse extends FormulaNodeUnary {

    var $type = 'number';

    function evaluate($values) {
        $value = $this->node->evaluate($values);
        if ($value == 0.0) {
            throw new FormulaException('eval error');
        }
        return 1.0 / $value;
    }
}

class FormulaNodeConstant extends FormulaNode {

    var $type = 'number';
    var $value;

    function __construct($value) {
        $this->value = $value;
    }

    function evaluate($values) {
        return (float) $this->value;
    }
}

class FormulaNodeVariable extends FormulaNode {

    var $type = 'number';
    var $id;

    function __construct($id) {
        $this->id = $id;
    }

    function evaluate($values) {
        if (!isset($values[$this->id])) {
            throw new FormulaException('eval error');
        }
        return $values[$this->id];
    }

    function variables($vars=array()) {
        if (!in_array($this->id, $vars)) {
            $vars[] = $this->id;
        }
        return $vars;
    }
}

class FormulaNodeFunction extends FormulaNodeNary {

    var $function;

    function __construct($id, $nodes) {
        $this->function = formula_function($id);
        $this->nodes = $nodes;
        $this->type = $this->function->type;

        if ($this->function->params_count and
            !in_array(count($nodes), $this->function->params_count)) {
            throw new FormulaException('syntax error');
        }

        foreach ($nodes as $node) {
            $node->check($this->function->params_type);
        }
    }

    function evaluate($values) {
        $args = array();
        foreach ($this->nodes as $node) {
            $args[] = $node->evaluate($values);
        }
        return $this->function->evaluate($args);
    }
}
