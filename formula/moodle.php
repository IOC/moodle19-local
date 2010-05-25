<?php

require_once(dirname(__FILE__) . '/lib.php');

class calc_formula_local {

    var $_node;
    var $_params;

    function calc_formula_local($formula, $params=false) {
        if (strpos($formula, '=') !== 0) {
            $this->_error = "missing leading '='";
            return;
        }
        $formula = substr($formula, 1);
        try {
            $this->_node = formula_parse($formula);
        } catch (FormulaException $e) {
            $this->_error = $e->getMessage();
            return;
        }
        $this->set_params($params);
    }

    function set_params($params) {
        $this->_params = $params ? (array) $params : array();
    }

    function evaluate() {
        if ($this->_node) {
            try {
                return $this->_node->evaluate($this->_params);
            } catch (FormulaException $e) {
                $this->_error = $e->getMessaage();
            }
        }
        return false;
    }

    function get_error() {
        return $this->_error;
    }
}
