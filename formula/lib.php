<?php

require_once(dirname(__FILE__) . '/nodes.php');
require_once(dirname(__FILE__) . '/functions.php');

class FormulaException extends Exception {}

class FormulaLexer {

    var $regexp;

    function __construct() {
        $symbols = array(
            'number' => '\d+(?:\.\d+)?|\.\d+',
            'if' => 'if\b',
            'else' => 'else\b',
            'and' => 'and\b',
            'or' => 'or\b',
            'not' => 'not\b',
            'id' => '[a-zA-Z_][a-zA-Z0-9_]*',
            'plus' => '\+',
            'minus' => '-',
            'comp' => '<=|>=|!=|<|>|=',
            'asterisk' => '\*',
            'slash' => '\/',
            'caret' => '\^',
            'openpar' => '\(',
            'closepar' => '\)',
            'comma' => ',',
            'space' => '\s+',
            'error' => '.+',
        );
        $regexp_parts = array();
        foreach ($symbols as $type => $regexp) {
            $regexp_parts[] = "(?P<$type>$regexp)";
        }
        $this->regexp = '/' . implode('|', $regexp_parts) . '/i';
    }

    function tokenize($input) {
        preg_match_all($this->regexp, $input, $matches, PREG_SET_ORDER);
        $tokens = array();
        foreach ($matches as $match) {
            end($match);
            $value = prev($match);
            $type = key($match);
            if ($type == 'error') {
                throw new FormulaException('token error');
            } elseif($type != 'space') {
                $tokens[] = new FormulaToken($type, strtolower($value));
            }
        }
        $tokens[] = new FormulaToken('eos');
        return $tokens;
    }
}

class FormulaParser {

    var $lexer;
    var $tokens;
    var $next_token;

    function __construct($lexer) {
        $this->lexer = $lexer;
    }

    function advance($type=false) {
        $token = $this->next_token;
        if ($type and $token->type != $type) {
            throw new FormulaException('syntax error');
        }
        $this->next_token = next($this->tokens);
        return $token;
    }

    function lookahead($type) {
        return ($this->next_token->type == $type);
    }

    function parse($input) {
        $this->tokens = $this->lexer->tokenize($input);
        $this->next_token = current($this->tokens);
        $node = $this->expr_conditional();
        $this->advance('eos');
        $node->check('number');
        return $node;
    }

    function expr_conditional() {
        $node = $this->expr_disjunction();
        if ($this->lookahead('if')) {
            $this->advance('if');
            $node_cond = $this->expr_conditional();
            $this->advance('else');
            $node_else = $this->expr_conditional();
            $node = new FormulaNodeConditional($node_cond, $node, $node_else);
        }
        return $node;
    }

    function expr_disjunction() {
        $nodes = array($this->expr_conjunction());
        while ($this->lookahead('or')) {
            $this->advance('or');
            $nodes[] = $this->expr_conjunction();
        }
        return (count($nodes) > 1 ?
                new FormulaNodeDisjunction($nodes) : $nodes[0]);
    }

    function expr_conjunction() {
        $nodes = array($this->expr_complement());
        while ($this->lookahead('and')) {
            $this->advance('and');
            $nodes[] = $this->expr_complement();
        }
        return (count($nodes) > 1 ?
                new FormulaNodeConjunction($nodes) : $nodes[0]);
    }

    function expr_complement() {
        $complement = false;
        while ($this->lookahead('not')) {
            $this->advance('not');
            $complement = !$complement;
        }
        $node = $this->expr_comparison();
        return $complement ? new FormulaNodeComplement($node) : $node;
    }

    function expr_comparison() {
        $left = $this->expr_sum();
        $comps = array();
        while ($this->lookahead('comp')) {
            $token = $this->advance('comp');
            $right = $this->expr_sum();
            $comps[] = new FormulaNodeComparison($token->value, $left, $right);
            $left = $right;
        }
        if (count($comps) == 0) {
            return $left;
        } elseif (count($comps) == 1) {
            return $comps[0];
        } else {
            return new FormulaNodeConjunction($comps);
        }
    }

    function expr_sum() {
        $nodes = array($this->expr_product());
        while (true) {
            if ($this->lookahead('plus')) {
                $this->advance('plus');
                $nodes[] = $this->expr_product();
            } elseif ($this->lookahead('minus')) {
                $this->advance('minus');
                $nodes[] = new FormulaNodeNegative($this->expr_product());
            } else {
                return (count($nodes) > 1 ?
                        new FormulaNodeSum($nodes) : $nodes[0]);
            }
        }
    }

    function expr_product() {
        $nodes = array($this->expr_negative());
        while (true) {
             if ($this->lookahead('asterisk')) {
                $this->advance('asterisk');
                $nodes[] = $this->expr_negative();
            } elseif ($this->lookahead('slash')) {
                $this->advance('slash');
                $nodes[] = new FormulaNodeInverse($this->expr_negative());
             } else {
                 return (count($nodes) > 1 ?
                         new FormulaNodeProduct($nodes) : $nodes[0]);
             }
        }
    }

    function expr_negative() {
        $negative = false;
        while (true) {
            if ($this->lookahead('minus')) {
                $this->advance('minus');
                $negative = !$negative;
            } elseif ($this->lookahead('plus')) {
                $this->advance('plus');
            } else {
                $node = $this->expr_power();
                return $negative ? new FormulaNodeNegative($node) : $node;
            }
        }
    }

    function expr_power() {
        $node = $this->expr_atom();
        while ($this->lookahead('caret')) {
            $this->advance('caret');
            $node = new FormulaNodePower($node, $this->expr_atom());
        }
        return $node;
    }


    function expr_atom() {
        if ($this->lookahead('id')) {
            $token = $this->advance('id');
            if ($this->lookahead('openpar')) {
                $nodes = $this->expr_args();
                $node = new FormulaNodeFunction($token->value, $nodes);
            } else {
                $node = new FormulaNodeVariable($token->value);
            }
        } elseif ($this->lookahead('number')) {
            $token = $this->advance('number');
            $node = new FormulaNodeConstant($token->value);
        } else {
            $this->advance('openpar');
            $node = $this->expr_conditional();
            $this->advance('closepar');
        }
        return $node;
    }

    function expr_args() {
        $nodes = array();
        $this->advance('openpar');
        if (!$this->lookahead('closepar')) {
            $nodes[] = $this->expr_conditional();
            while ($this->lookahead('comma')) {
                $this->advance('comma');
                $nodes[] = $this->expr_conditional();
            }
        }
        $this->advance('closepar');
        return $nodes;
    }
}

class FormulaToken {

    var $type;
    var $value;

    function __construct($type, $value='') {
        $this->type = $type;
        $this->value = $value;
    }
}

function formula_parse($calculation) {
    static $parser = false;
    if (!$parser) {
        $lexer = new FormulaLexer();
        $parser = new FormulaParser($lexer);
    }
    return $parser->parse($calculation);
}

